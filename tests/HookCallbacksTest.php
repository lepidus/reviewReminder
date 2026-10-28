<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewRequest;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\tests\PKPTestCase;

class HookCallbacksTest extends PKPTestCase
{
    private const CONTEXT_PATH = 'publicknowledge';
    private const SUBMISSION_ID = 7;
    private const REVIEW_ASSIGNMENT_ID = 11;
    private const NAMESAKE_REVIEW_ASSIGNMENT_ID = 12;
    private const NEW_ROUND_DUE_DATE = '2027-03-15 00:00:00';
    private const NEW_ROUND_CALENDAR_START = 'DTSTART;VALUE=DATE:20270315';
    private const NAMESAKE_DUE_DATE = '2027-04-20 00:00:00';
    private const NAMESAKE_CALENDAR_START = 'DTSTART;VALUE=DATE:20270420';
    private const END_OF_MONTH_DUE_DATE = '2027-02-28 00:00:00';
    private const END_OF_MONTH_CALENDAR_START = 'DTSTART;VALUE=DATE:20270228';
    private const END_OF_MONTH_CALENDAR_END = 'DTEND;VALUE=DATE:20270301';
    private const OVERDUE_DUE_DATE = '2020-01-10 00:00:00';
    private const OVERDUE_CALENDAR_START = 'DTSTART;VALUE=DATE:20200110';
    private const OVERDUE_CALENDAR_END = 'DTEND;VALUE=DATE:20200111';
    private const INJECTED_CALENDAR_PROPERTY = 'X-INJECTED-PROPERTY:injected';
    private const ONE_CLICK_REVIEW_URL = 'http://localhost/index.php/publicknowledge/invitation/accept?id=1&key=oneClickKey';
    private const REVIEW_PAGE_PATH = '/reviewer/submission?submissionId=7';
    private const CALENDAR_EVENT_NAME = 'SUMMARY:Review deadline: Developing efficacy beliefs in the classroom (Journal of Public Knowledge)';

    private $newReviewRoundId;
    private $newReviewAssignmentId;
    private $originalNamesOfRenamedReviewer = [];
    private $originalNamesakeDueDate;
    private $originalSubmissionTitle;
    private $viewDataCallbackBeforeTest;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Locale::registerPath('plugins/generic/reviewReminder/locale');
        $this->createContextRequest();
        $this->viewDataCallbackBeforeTest = Mailable::$viewDataCallback;
    }

    protected function tearDown(): void
    {
        if ($this->newReviewAssignmentId) {
            DB::table('review_assignments')->where('review_id', $this->newReviewAssignmentId)->delete();
        }

        if ($this->newReviewRoundId) {
            DB::table('review_rounds')->where('review_round_id', $this->newReviewRoundId)->delete();
        }

        foreach ($this->originalNamesOfRenamedReviewer as $originalName) {
            DB::table('user_settings')
                ->where('user_id', $originalName->user_id)
                ->where('locale', $originalName->locale)
                ->where('setting_name', $originalName->setting_name)
                ->update(['setting_value' => $originalName->setting_value]);
        }

        if ($this->originalNamesakeDueDate) {
            DB::table('review_assignments')
                ->where('review_id', self::NAMESAKE_REVIEW_ASSIGNMENT_ID)
                ->update(['date_due' => $this->originalNamesakeDueDate]);
        }

        if ($this->originalSubmissionTitle) {
            $this->updateSubmissionTitle($this->originalSubmissionTitle);
        }

        Mailable::$viewDataCallback = $this->viewDataCallbackBeforeTest;
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createContextRequest(): void
    {
        Registry::delete('request');
        $_SERVER['PATH_INFO'] = self::CONTEXT_PATH . '/index/index';
        $application = Application::get();
        $request = $application->getRequest();

        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $request->setRouter($router);
    }

    private function assignReviewerToNewReviewRound(ReviewAssignment $currentAssignment, string $dueDate = self::NEW_ROUND_DUE_DATE): ReviewAssignment
    {
        $this->newReviewRoundId = DB::table('review_rounds')->insertGetId([
            'submission_id' => self::SUBMISSION_ID,
            'stage_id' => $currentAssignment->getStageId(),
            'round' => $currentAssignment->getRound() + 1,
            'status' => 0,
        ], 'review_round_id');

        $newAssignment = clone $currentAssignment;
        $newAssignment->setReviewRoundId($this->newReviewRoundId);
        $newAssignment->setRound($currentAssignment->getRound() + 1);
        $newAssignment->setDateDue($dueDate);
        $newAssignment->setDateConfirmed(null);
        $this->newReviewAssignmentId = Repo::reviewAssignment()->dao->insert($newAssignment);

        return Repo::reviewAssignment()->get($this->newReviewAssignmentId);
    }

    private function giveReviewerTheNameOf(int $reviewerId, int $namesakeId): void
    {
        $this->originalNamesOfRenamedReviewer = DB::table('user_settings')
            ->where('user_id', $reviewerId)
            ->whereIn('setting_name', ['givenName', 'familyName'])
            ->get()
            ->all();

        $namesakeNames = DB::table('user_settings')
            ->where('user_id', $namesakeId)
            ->whereIn('setting_name', ['givenName', 'familyName'])
            ->get();
        foreach ($namesakeNames as $namesakeName) {
            DB::table('user_settings')
                ->where('user_id', $reviewerId)
                ->where('locale', $namesakeName->locale)
                ->where('setting_name', $namesakeName->setting_name)
                ->update(['setting_value' => $namesakeName->setting_value]);
        }
    }

    private function changeNamesakeDueDate(): void
    {
        $this->originalNamesakeDueDate = DB::table('review_assignments')
            ->where('review_id', self::NAMESAKE_REVIEW_ASSIGNMENT_ID)
            ->value('date_due');
        DB::table('review_assignments')
            ->where('review_id', self::NAMESAKE_REVIEW_ASSIGNMENT_ID)
            ->update(['date_due' => self::NAMESAKE_DUE_DATE]);
    }

    private function updateSubmissionTitle(string $title): void
    {
        $publicationId = Repo::submission()->get(self::SUBMISSION_ID)->getData('currentPublicationId');
        DB::table('publication_settings')
            ->where('publication_id', $publicationId)
            ->where('locale', 'en')
            ->where('setting_name', 'title')
            ->update(['setting_value' => $title]);
    }

    private function injectCalendarPropertyIntoSubmissionTitle(): void
    {
        $this->originalSubmissionTitle = Repo::submission()->get(self::SUBMISSION_ID)->getCurrentPublication()->getLocalizedTitle('en');
        $this->updateSubmissionTitle("Injected title\r\n" . self::INJECTED_CALENDAR_PROPERTY);
    }

    private function sendReviewRequest(ReviewAssignment $reviewAssignment, ?string $oneClickReviewUrl = null): Mailable
    {
        $submission = Repo::submission()->get(self::SUBMISSION_ID);
        $context = app()->get('context')->get($submission->getData('contextId'));
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());

        $mailable = new ReviewRequest($context, $submission, $reviewAssignment);
        $mailable->recipients([$reviewer]);
        $mailable->setData('en');
        if ($oneClickReviewUrl) {
            $mailable->buildViewDataUsing(fn () => [ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL => $oneClickReviewUrl]);
        }

        (new HookCallbacks())->attachCalendarInvite('Mailable::build', $mailable);

        return $mailable;
    }

    private function getCalendarInvite(Mailable $mailable): string
    {
        $this->assertCount(1, $mailable->rawAttachments);
        return str_replace("\r\n ", '', $mailable->rawAttachments[0]['data']);
    }

    private function getCalendarProperty(Mailable $mailable, string $propertyName): string
    {
        $calendarLines = explode("\r\n", $this->getCalendarInvite($mailable));
        $propertyLines = array_values(preg_grep('/^' . $propertyName . ':/', $calendarLines));
        $this->assertCount(1, $propertyLines);

        return substr($propertyLines[0], strlen($propertyName) + 1);
    }

    public function testShouldAttachCalendarInviteWithoutWritingFileToDisk(): void
    {
        $mailable = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));

        $this->assertEmpty($mailable->attachments);
        $this->assertCount(1, $mailable->rawAttachments);
        $this->assertEquals('invite.ics', $mailable->rawAttachments[0]['name']);
        $this->assertEquals('text/calendar', $mailable->rawAttachments[0]['options']['mime']);
        $this->assertStringStartsWith('BEGIN:VCALENDAR', $mailable->rawAttachments[0]['data']);
    }

    public function testShouldUseDueDateOfReviewRoundBeingRequested(): void
    {
        $newRoundAssignment = $this->assignReviewerToNewReviewRound(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));

        $mailable = $this->sendReviewRequest($newRoundAssignment);

        $this->assertStringContainsString(self::NEW_ROUND_CALENDAR_START, $this->getCalendarInvite($mailable));
    }

    public function testShouldUseDueDateOfRequestedReviewerWhenAnotherReviewerHasTheSameName(): void
    {
        $namesakeAssignment = Repo::reviewAssignment()->get(self::NAMESAKE_REVIEW_ASSIGNMENT_ID);
        $reviewAssignment = Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID);
        $this->giveReviewerTheNameOf($reviewAssignment->getReviewerId(), $namesakeAssignment->getReviewerId());
        $this->changeNamesakeDueDate();

        $mailable = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::NAMESAKE_REVIEW_ASSIGNMENT_ID));

        $this->assertStringContainsString(self::NAMESAKE_CALENDAR_START, $this->getCalendarInvite($mailable));
    }

    public function testShouldKeepSubmissionTitleInsideCalendarInviteDescription(): void
    {
        $this->injectCalendarPropertyIntoSubmissionTitle();

        $mailable = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));

        $calendarLines = explode("\r\n", $this->getCalendarInvite($mailable));
        $injectedLines = array_filter(
            $calendarLines,
            fn (string $line) => str_starts_with($line, self::INJECTED_CALENDAR_PROPERTY)
        );
        $this->assertEmpty($injectedLines);
    }

    public function testShouldLinkCalendarInviteToReviewPageInsteadOfOneClickReviewerAccess(): void
    {
        $mailable = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID), self::ONE_CLICK_REVIEW_URL);

        $calendarInvite = $this->getCalendarInvite($mailable);
        $this->assertStringNotContainsString(self::ONE_CLICK_REVIEW_URL, $calendarInvite);
        $this->assertStringContainsString(self::REVIEW_PAGE_PATH, $calendarInvite);
    }

    public function testShouldNameCalendarEventAfterTheReviewedSubmissionAndJournal(): void
    {
        $mailable = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));

        $this->assertContains(self::CALENDAR_EVENT_NAME, explode("\r\n", $this->getCalendarInvite($mailable)));
    }

    public function testShouldPlaceCalendarEventOnTheReviewDueDateOnly(): void
    {
        $reviewAssignment = $this->assignReviewerToNewReviewRound(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID), self::END_OF_MONTH_DUE_DATE);

        $mailable = $this->sendReviewRequest($reviewAssignment);

        $calendarLines = explode("\r\n", $this->getCalendarInvite($mailable));
        $this->assertContains(self::END_OF_MONTH_CALENDAR_START, $calendarLines);
        $this->assertContains(self::END_OF_MONTH_CALENDAR_END, $calendarLines);
    }

    public function testShouldPlaceCalendarEventOnTheDueDateOfAnOverdueReview(): void
    {
        $reviewAssignment = $this->assignReviewerToNewReviewRound(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID), self::OVERDUE_DUE_DATE);

        $mailable = $this->sendReviewRequest($reviewAssignment);

        $calendarLines = explode("\r\n", $this->getCalendarInvite($mailable));
        $this->assertContains(self::OVERDUE_CALENDAR_START, $calendarLines);
        $this->assertContains(self::OVERDUE_CALENDAR_END, $calendarLines);
    }

    public function testShouldKeepCalendarEventUidAcrossEmailsOfTheSameReview(): void
    {
        $firstEmail = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));
        $secondEmail = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));

        $this->assertMatchesRegularExpression('/^review-assignment-' . self::REVIEW_ASSIGNMENT_ID . '@.+$/', $this->getCalendarProperty($firstEmail, 'UID'));
        $this->assertEquals($this->getCalendarProperty($firstEmail, 'UID'), $this->getCalendarProperty($secondEmail, 'UID'));
    }

    public function testShouldUseDifferentCalendarEventUidForAnotherReview(): void
    {
        $reviewEmail = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));
        $anotherReviewEmail = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::NAMESAKE_REVIEW_ASSIGNMENT_ID));

        $this->assertNotEquals($this->getCalendarProperty($reviewEmail, 'UID'), $this->getCalendarProperty($anotherReviewEmail, 'UID'));
    }

    public function testShouldIncreaseCalendarEventSequenceOnEachEmail(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $firstEmail = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));
        Carbon::setTestNow('2026-10-28 10:00:00');
        $laterEmail = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID));

        $this->assertGreaterThan((int) $this->getCalendarProperty($firstEmail, 'SEQUENCE'), (int) $this->getCalendarProperty($laterEmail, 'SEQUENCE'));
    }
}

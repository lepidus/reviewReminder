<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
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
    private const NEW_ROUND_CALENDAR_END = 'DTEND:20270315T235959';
    private const NAMESAKE_DUE_DATE = '2027-04-20 00:00:00';
    private const NAMESAKE_CALENDAR_END = 'DTEND:20270420T235959';
    private const INJECTED_CALENDAR_PROPERTY = 'X-INJECTED-PROPERTY:injected';
    private const ONE_CLICK_REVIEW_URL = 'http://localhost/index.php/publicknowledge/invitation/accept?id=1&key=oneClickKey';

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

    private function assignReviewerToNewReviewRound(ReviewAssignment $currentAssignment): ReviewAssignment
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
        $newAssignment->setDateDue(self::NEW_ROUND_DUE_DATE);
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
        return $mailable->rawAttachments[0]['data'];
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

        $this->assertStringContainsString(self::NEW_ROUND_CALENDAR_END, $this->getCalendarInvite($mailable));
    }

    public function testShouldUseDueDateOfRequestedReviewerWhenAnotherReviewerHasTheSameName(): void
    {
        $namesakeAssignment = Repo::reviewAssignment()->get(self::NAMESAKE_REVIEW_ASSIGNMENT_ID);
        $reviewAssignment = Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID);
        $this->giveReviewerTheNameOf($reviewAssignment->getReviewerId(), $namesakeAssignment->getReviewerId());
        $this->changeNamesakeDueDate();

        $mailable = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::NAMESAKE_REVIEW_ASSIGNMENT_ID));

        $this->assertStringContainsString(self::NAMESAKE_CALENDAR_END, $this->getCalendarInvite($mailable));
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

    public function testShouldLinkCalendarInviteToOneClickReviewerAccessWhenEnabled(): void
    {
        $mailable = $this->sendReviewRequest(Repo::reviewAssignment()->get(self::REVIEW_ASSIGNMENT_ID), self::ONE_CLICK_REVIEW_URL);

        $this->assertStringContainsString(self::ONE_CLICK_REVIEW_URL, $this->getCalendarInvite($mailable));
    }
}

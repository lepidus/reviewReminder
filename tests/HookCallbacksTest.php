<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\ReviewReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\ReviewerAction;
use PKP\submission\reviewer\form\PKPReviewerReviewStep1Form;
use PKP\tests\PKPTestCase;

class HookCallbacksTest extends PKPTestCase
{
    private const CONTEXT_PATH = 'publicknowledge';
    private const SUBMISSION_ID = 7;
    private const REVIEWER_ID = 9;
    private const NAMESAKE_REVIEWER_ID = 10;
    private const NEW_ROUND_DUE_DATE = '2027-03-15 00:00:00';
    private const NEW_ROUND_CALENDAR_START = 'DTSTART;VALUE=DATE:20270315';
    private const PREVIOUS_ROUND_CALENDAR_START = 'DTSTART;VALUE=DATE:20260828';
    private const END_OF_MONTH_DUE_DATE = '2027-02-28 00:00:00';
    private const END_OF_MONTH_CALENDAR_START = 'DTSTART;VALUE=DATE:20270228';
    private const END_OF_MONTH_CALENDAR_END = 'DTEND;VALUE=DATE:20270301';
    private const OVERDUE_DUE_DATE = '2020-01-10 00:00:00';
    private const OVERDUE_CALENDAR_START = 'DTSTART;VALUE=DATE:20200110';
    private const OVERDUE_CALENDAR_END = 'DTEND;VALUE=DATE:20200111';
    private const INJECTED_CALENDAR_PROPERTY = 'X-INJECTED-PROPERTY:injected';
    private const CONTEXT_ID = 1;
    private const ONE_CLICK_ACCESS_SETTING = 'reviewerAccessKeysEnabled';
    private const REVIEW_PAGE_PATH = '/reviewer/submission?submissionId=7';
    private const CALENDAR_EVENT_NAME = 'SUMMARY:Review deadline: Developing efficacy beliefs in the classroom (Journal of Public Knowledge)';

    private $reviewAssignmentDao;
    private $newReviewRoundId;
    private $newReviewAssignmentId;
    private $originalNamesOfRenamedReviewer = [];
    private $confirmedReviewAssignmentIds = [];
    private $originalSubmissionTitle;
    private $lastEventLogIdBeforeTest;
    private $oneClickAccessSettingBeforeTest;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        Locale::registerPath('plugins/generic/reviewReminder/locale');
        $this->lastEventLogIdBeforeTest = DB::table('event_log')->max('log_id');
        $this->oneClickAccessSettingBeforeTest = DB::table('journal_settings')
            ->where('journal_id', self::CONTEXT_ID)
            ->where('setting_name', self::ONE_CLICK_ACCESS_SETTING)
            ->value('setting_value');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::table('access_keys')->whereIn('assoc_id', $this->confirmedReviewAssignmentIds)->delete();
        $this->setOneClickReviewerAccess($this->oneClickAccessSettingBeforeTest);

        $createdEventLogIds = DB::table('event_log')->where('log_id', '>', $this->lastEventLogIdBeforeTest)->pluck('log_id');
        DB::table('event_log_settings')->whereIn('log_id', $createdEventLogIds)->delete();
        DB::table('event_log')->whereIn('log_id', $createdEventLogIds)->delete();

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

        if ($this->originalSubmissionTitle) {
            $this->updateSubmissionTitle($this->originalSubmissionTitle);
        }

        parent::tearDown();
    }

    private function createContextRequest(): PKPRequest
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

        return $request;
    }

    private function setOneClickReviewerAccess(?string $value): void
    {
        DB::table('journal_settings')
            ->where('journal_id', self::CONTEXT_ID)
            ->where('setting_name', self::ONE_CLICK_ACCESS_SETTING)
            ->delete();
        if ($value !== null) {
            DB::table('journal_settings')->insert([
                'journal_id' => self::CONTEXT_ID,
                'locale' => '',
                'setting_name' => self::ONE_CLICK_ACCESS_SETTING,
                'setting_value' => $value,
            ]);
        }
    }

    private function getCurrentReviewAssignment(int $reviewerId): ReviewAssignment
    {
        return $this->reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer(self::SUBMISSION_ID, $reviewerId);
    }

    private function assignReviewerToNewReviewRound(int $reviewerId, string $dueDate = self::NEW_ROUND_DUE_DATE): ReviewAssignment
    {
        $currentAssignment = $this->getCurrentReviewAssignment($reviewerId);
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
        $this->reviewAssignmentDao->insertObject($newAssignment);
        $this->newReviewAssignmentId = $newAssignment->getId();

        return $this->reviewAssignmentDao->getById($this->newReviewAssignmentId);
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
        $this->originalSubmissionTitle = Repo::submission()->get(self::SUBMISSION_ID)->getLocalizedTitle('en');
        $this->updateSubmissionTitle("Injected title\r\n" . self::INJECTED_CALENDAR_PROPERTY);
    }

    private function confirmReview(ReviewAssignment $reviewAssignment, ?HookCallbacks $hookCallbacks = null): void
    {
        $this->confirmedReviewAssignmentIds[] = $reviewAssignment->getId();
        $submission = Repo::submission()->get(self::SUBMISSION_ID);
        $mailable = (new ReviewerAction())->getResponseEmail($submission, $reviewAssignment, false, null);
        $request = $this->createContextRequest();

        ($hookCallbacks ?? new HookCallbacks())->sendReviewReminder('ReviewerAction::confirmReview', [$request, $submission, $mailable, false]);
    }

    private function acceptThroughReviewForm(ReviewAssignment $reviewAssignment): void
    {
        $hookCallbacks = new HookCallbacks();
        $reviewForm = new PKPReviewerReviewStep1Form($this->createContextRequest(), Repo::submission()->get(self::SUBMISSION_ID), $reviewAssignment);
        $formVariables = [];
        $hookCallbacks->rememberReviewBeingAccepted('pkpreviewerreviewstep1form::readuservars', [$reviewForm, &$formVariables]);

        $this->confirmReview($reviewAssignment, $hookCallbacks);
    }

    private function getSentReminder(): ReviewReminder
    {
        $sentReminders = Mail::sent(ReviewReminder::class);
        $this->assertCount(1, $sentReminders);
        return $sentReminders->first();
    }

    public function testShouldRemindDueDateOfReviewRoundBeingAccepted(): void
    {
        $newRoundAssignment = $this->assignReviewerToNewReviewRound(self::REVIEWER_ID);

        $this->confirmReview($newRoundAssignment);

        $calendarInvite = $this->getSentReminder()->rawAttachments[0];
        $this->assertStringContainsString(self::NEW_ROUND_CALENDAR_START, $calendarInvite['data']);
    }

    public function testShouldRemindReviewerWhoAcceptedWhenAnotherReviewerHasTheSameName(): void
    {
        $this->giveReviewerTheNameOf(self::REVIEWER_ID, self::NAMESAKE_REVIEWER_ID);
        $namesake = Repo::user()->get(self::NAMESAKE_REVIEWER_ID);

        $this->confirmReview($this->getCurrentReviewAssignment(self::NAMESAKE_REVIEWER_ID));

        $this->assertEquals($namesake->getEmail(), $this->getSentReminder()->to[0]['address']);
    }

    public function testShouldAttachCalendarInviteWithoutWritingFileToDisk(): void
    {
        $this->confirmReview($this->getCurrentReviewAssignment(self::REVIEWER_ID));

        $reminder = $this->getSentReminder();
        $this->assertEmpty($reminder->attachments);
        $this->assertCount(1, $reminder->rawAttachments);
        $this->assertEquals('invite.ics', $reminder->rawAttachments[0]['name']);
        $this->assertEquals('text/calendar', $reminder->rawAttachments[0]['options']['mime']);
        $this->assertStringStartsWith('BEGIN:VCALENDAR', $reminder->rawAttachments[0]['data']);
    }

    public function testShouldKeepSubmissionTitleInsideCalendarInviteDescription(): void
    {
        $this->injectCalendarPropertyIntoSubmissionTitle();

        $this->confirmReview($this->getCurrentReviewAssignment(self::REVIEWER_ID));

        $calendarLines = explode("\r\n", $this->getSentReminder()->rawAttachments[0]['data']);
        $injectedLines = array_filter(
            $calendarLines,
            fn (string $line) => str_starts_with($line, self::INJECTED_CALENDAR_PROPERTY)
        );
        $this->assertEmpty($injectedLines);
    }

    public function testShouldRecordReminderInActivityLogOfAcceptedReview(): void
    {
        $reviewAssignment = $this->getCurrentReviewAssignment(self::REVIEWER_ID);

        $this->confirmReview($reviewAssignment);

        $recordedReminders = Repo::eventLog()
            ->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION, [self::SUBMISSION_ID])
            ->getMany()
            ->filter(
                fn ($entry) => $entry->getId() > $this->lastEventLogIdBeforeTest
                    && $entry->getData('eventType') == PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO
            )
            ->values()
            ->all();
        $this->assertCount(1, $recordedReminders);
        $this->assertEquals($reviewAssignment->getId(), $recordedReminders[0]->getData('reviewAssignmentId'));
        $this->assertEquals(self::REVIEWER_ID, $recordedReminders[0]->getData('recipientId'));
        $this->assertEquals('plugins.generic.reviewReminder.event.reviewReminderSent', $recordedReminders[0]->getData('message'));
        $this->assertEquals(
            'The Review Reminder plugin sent ' . $recordedReminders[0]->getData('recipientName') . " a reminder with the review's calendar invitation",
            $recordedReminders[0]->getTranslatedMessage('en')
        );
    }

    public function testShouldNotChangeReviewReminderDateWhenRecordingAcceptedReview(): void
    {
        $reviewAssignment = $this->getCurrentReviewAssignment(self::REVIEWER_ID);
        $reminderDateBeforeAcceptance = DB::table('review_assignments')->where('review_id', $reviewAssignment->getId())->value('date_reminded');

        $this->confirmReview($reviewAssignment);

        $this->assertCount(1, Mail::sent(ReviewReminder::class));
        $this->assertEquals(
            $reminderDateBeforeAcceptance,
            DB::table('review_assignments')->where('review_id', $reviewAssignment->getId())->value('date_reminded')
        );
    }

    public function testShouldNameCalendarEventAfterTheAcceptedSubmissionAndJournal(): void
    {
        $this->confirmReview($this->getCurrentReviewAssignment(self::REVIEWER_ID));

        $this->assertContains(self::CALENDAR_EVENT_NAME, explode("\r\n", $this->getSentReminder()->rawAttachments[0]['data']));
    }

    public function testShouldPlaceCalendarEventOnTheReviewDueDateOnly(): void
    {
        $this->confirmReview($this->assignReviewerToNewReviewRound(self::REVIEWER_ID, self::END_OF_MONTH_DUE_DATE));

        $calendarLines = explode("\r\n", $this->getSentReminder()->rawAttachments[0]['data']);
        $this->assertContains(self::END_OF_MONTH_CALENDAR_START, $calendarLines);
        $this->assertContains(self::END_OF_MONTH_CALENDAR_END, $calendarLines);
    }

    public function testShouldPlaceCalendarEventOnTheDueDateOfAnOverdueReview(): void
    {
        $this->confirmReview($this->assignReviewerToNewReviewRound(self::REVIEWER_ID, self::OVERDUE_DUE_DATE));

        $calendarLines = explode("\r\n", $this->getSentReminder()->rawAttachments[0]['data']);
        $this->assertContains(self::OVERDUE_CALENDAR_START, $calendarLines);
        $this->assertContains(self::OVERDUE_CALENDAR_END, $calendarLines);
    }

    public function testShouldNotCreateAccessKeyOnReviewAcceptanceWhenOneClickReviewerAccessIsEnabled(): void
    {
        $this->setOneClickReviewerAccess('1');
        $reviewAssignment = $this->getCurrentReviewAssignment(self::REVIEWER_ID);

        $this->confirmReview($reviewAssignment);

        $this->assertFalse(DB::table('access_keys')->where('assoc_id', $reviewAssignment->getId())->exists());
    }

    public function testShouldLinkCalendarInviteToReviewPageWhenOneClickReviewerAccessIsEnabled(): void
    {
        $this->setOneClickReviewerAccess('1');

        $this->confirmReview($this->getCurrentReviewAssignment(self::REVIEWER_ID));

        $calendarInvite = str_replace("\r\n ", '', $this->getSentReminder()->rawAttachments[0]['data']);
        $this->assertStringContainsString(self::REVIEW_PAGE_PATH, $calendarInvite);
        $this->assertStringNotContainsString('key=', $calendarInvite);
    }

    public function testShouldRemindDueDateOfPreviousRoundAcceptedAfterReviewerIsAssignedToANewRound(): void
    {
        $previousRoundAssignment = $this->getCurrentReviewAssignment(self::REVIEWER_ID);
        $this->assignReviewerToNewReviewRound(self::REVIEWER_ID);

        $this->acceptThroughReviewForm($previousRoundAssignment);

        $this->assertStringContainsString(self::PREVIOUS_ROUND_CALENDAR_START, $this->getSentReminder()->rawAttachments[0]['data']);
    }
}

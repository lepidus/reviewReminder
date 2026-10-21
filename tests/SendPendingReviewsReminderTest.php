<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;
use APP\plugins\generic\reviewReminder\classes\tasks\SendPendingReviewsReminder;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\Dispatcher;
use PKP\core\PKPApplication;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\tests\PKPTestCase;
use Symfony\Component\Mailer\Exception\TransportException;

class SendPendingReviewsReminderTest extends PKPTestCase
{
    private const PLUGIN_NAME = 'reviewreminderplugin';
    private const FIRST_CONTEXT_ID = 1;
    private const FIRST_CONTEXT_REVIEWERS = [
        'jjanssen@mailinator.com',
        'phudson@mailinator.com',
        'amccrae@mailinator.com',
        'agallego@mailinator.com',
        'shellier@mailinator.com',
        'cturner@mailinator.com',
        'skumar@mailinator.com',
        'jdoe@mailinator.com',
        'lvon@mailinator.com',
        'rsharif@mailinator.com',
    ];
    private const SHARED_REVIEWER_ID = 10;
    private const SECOND_CONTEXT_CONTACT_EMAIL = 'second.journal@mailinator.com';
    private const PUBLISHED_SUBMISSION_IDS = [1, 17];
    private const SUBMISSION_IDS_PAST_REVIEW_STAGE = [3, 5, 6, 9, 15, 19];
    private const SUBMISSION_IDS_UNDER_REVIEW = [7, 12, 20];
    private const PENDING_REVIEWS_REMINDER_MESSAGE = 'plugins.generic.reviewReminder.event.pendingReviewsReminderSent';
    private const WEEKLY_REMINDER_SETTING = 'pendingReviewsReminderEnabled';
    private const LAST_SENT_SETTING = 'pendingReviewsReminderLastSentAt';
    private const PREVIOUS_MONDAY_MIDNIGHT = '2026-10-12 00:00:00';
    private const MONDAY_MIDNIGHT = '2026-10-19 00:00:00';
    private const WEDNESDAY_AFTERNOON = '2026-10-21 15:00:00';
    private const FIRST_CONTEXT_REMINDER_SUBJECT = 'Pending Reviews Reminder - Journal of Public Knowledge';

    private $pluginSettingsDao;
    private $secondContextId;
    private $secondContextReviewerGroupId;
    private $declinedSubmissionId;
    private $newReviewRoundId;
    private $lastEventLogIdBeforeTest;
    private $enabledSettingBeforeTest;
    private $weeklyReminderSettingBeforeTest;
    private $lastSentSettingBeforeTest;
    private $mailManagerBeforeTest;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRoutedRequest();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->enabledSettingBeforeTest = $this->getPluginSetting(self::FIRST_CONTEXT_ID, 'enabled');
        $this->weeklyReminderSettingBeforeTest = $this->getPluginSetting(self::FIRST_CONTEXT_ID, self::WEEKLY_REMINDER_SETTING);
        $this->lastSentSettingBeforeTest = $this->getPluginSetting(self::FIRST_CONTEXT_ID, self::LAST_SENT_SETTING);
        $this->pluginSettingsDao->deleteSetting(self::FIRST_CONTEXT_ID, self::PLUGIN_NAME, self::LAST_SENT_SETTING);
        Carbon::setTestNow(self::MONDAY_MIDNIGHT);
        $this->lastEventLogIdBeforeTest = DB::table('event_log')->max('log_id');
        Locale::registerPath('plugins/generic/reviewReminder/locale');
        $this->mailManagerBeforeTest = Mail::isFake() ? Mail::getFacadeRoot()->manager : Mail::getFacadeRoot();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->restorePluginSetting(self::FIRST_CONTEXT_ID, 'enabled', $this->enabledSettingBeforeTest);
        $this->restorePluginSetting(self::FIRST_CONTEXT_ID, self::WEEKLY_REMINDER_SETTING, $this->weeklyReminderSettingBeforeTest);
        $this->restorePluginSetting(self::FIRST_CONTEXT_ID, self::LAST_SENT_SETTING, $this->lastSentSettingBeforeTest);
        Carbon::setTestNow();
        Mail::swap($this->mailManagerBeforeTest);

        if ($this->secondContextId) {
            $this->pluginSettingsDao->deleteSetting($this->secondContextId, self::PLUGIN_NAME, 'enabled');
            DB::table('user_groups')->where('user_group_id', $this->secondContextReviewerGroupId)->delete();
            DB::table('journals')->where('journal_id', $this->secondContextId)->delete();
        }

        if ($this->declinedSubmissionId) {
            DB::table('submissions')
                ->where('submission_id', $this->declinedSubmissionId)
                ->update(['status' => PKPSubmission::STATUS_QUEUED]);
        }

        if ($this->newReviewRoundId) {
            DB::table('review_rounds')->where('review_round_id', $this->newReviewRoundId)->delete();
        }

        $createdEventLogIds = DB::table('event_log')->where('log_id', '>', $this->lastEventLogIdBeforeTest)->pluck('log_id');
        DB::table('event_log_settings')->whereIn('log_id', $createdEventLogIds)->delete();
        DB::table('event_log')->whereIn('log_id', $createdEventLogIds)->delete();

        parent::tearDown();
    }

    private function failMailTransport(): void
    {
        $mailer = $this->mailManagerBeforeTest->mailer();
        Mail::shouldReceive('compileParams')->andReturnUsing(fn (...$args) => $mailer->compileParams(...$args));
        Mail::shouldReceive('send')->andThrow(new TransportException('Connection refused'));
    }

    private function createRoutedRequest(): void
    {
        Registry::delete('request');
        $_SERVER['PATH_INFO'] = 'index/index';
        $application = Application::get();
        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $application->getRequest()->setRouter($router);
    }

    private function getPluginSetting(int $contextId, string $settingName): ?object
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', $contextId)
            ->where('setting_name', $settingName)
            ->first();
    }

    private function restorePluginSetting(int $contextId, string $settingName, ?object $setting): void
    {
        if ($setting) {
            $value = $setting->setting_type === 'bool' ? (bool) $setting->setting_value : $setting->setting_value;
            $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, $settingName, $value, $setting->setting_type);
            return;
        }

        $this->pluginSettingsDao->deleteSetting($contextId, self::PLUGIN_NAME, $settingName);
    }

    private function enablePlugin(int $contextId): void
    {
        $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, 'enabled', true, 'bool');
    }

    private function disablePlugin(int $contextId): void
    {
        $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, 'enabled', false, 'bool');
    }

    private function setLastWeeklyReminder(int $contextId, string $dateTime): void
    {
        $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, self::LAST_SENT_SETTING, $dateTime, 'string');
    }

    private function setWeeklyReminderEnabled(int $contextId, ?bool $isEnabled): void
    {
        if ($isEnabled === null) {
            $this->pluginSettingsDao->deleteSetting($contextId, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING);
            return;
        }

        $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING, $isEnabled, 'bool');
    }

    private function createSecondContextWithSharedReviewer(): void
    {
        $this->secondContextId = DB::table('journals')->insertGetId([
            'path' => 'secondjournal',
            'primary_locale' => 'en',
            'enabled' => 1,
        ], 'journal_id');

        DB::table('journal_settings')->insert([
            ['journal_id' => $this->secondContextId, 'locale' => 'en', 'setting_name' => 'name', 'setting_value' => 'Second Journal'],
            ['journal_id' => $this->secondContextId, 'locale' => '', 'setting_name' => 'contactName', 'setting_value' => 'Second Contact'],
            ['journal_id' => $this->secondContextId, 'locale' => '', 'setting_name' => 'contactEmail', 'setting_value' => self::SECOND_CONTEXT_CONTACT_EMAIL],
            ['journal_id' => $this->secondContextId, 'locale' => '', 'setting_name' => 'supportedLocales', 'setting_value' => '["en"]'],
        ]);

        $this->secondContextReviewerGroupId = DB::table('user_groups')->insertGetId([
            'context_id' => $this->secondContextId,
            'role_id' => Role::ROLE_ID_REVIEWER,
        ], 'user_group_id');

        DB::table('user_user_groups')->insert([
            'user_group_id' => $this->secondContextReviewerGroupId,
            'user_id' => self::SHARED_REVIEWER_ID,
        ]);
    }

    private function declineSubmission(int $submissionId): void
    {
        $this->declinedSubmissionId = $submissionId;
        DB::table('submissions')
            ->where('submission_id', $submissionId)
            ->update(['status' => PKPSubmission::STATUS_DECLINED]);
    }

    private function startNewReviewRound(int $submissionId): void
    {
        $currentRound = DB::table('review_rounds')->where('submission_id', $submissionId)->first();
        $this->newReviewRoundId = DB::table('review_rounds')->insertGetId([
            'submission_id' => $submissionId,
            'stage_id' => $currentRound->stage_id,
            'round' => $currentRound->round + 1,
            'status' => $currentRound->status,
        ], 'review_round_id');
    }

    private function runTask(): bool
    {
        $task = new SendPendingReviewsReminder(new ReviewReminderPlugin());
        return (new ReflectionMethod($task, 'executeActions'))->invoke($task);
    }

    private function getRemindedSubmissionIds(): array
    {
        return Mail::sent(PendingReviewsReminder::class)
            ->flatMap(function (PendingReviewsReminder $email) {
                preg_match_all('/submissionId=(\d+)/', $email->viewData['submissionsList'], $matches);
                return array_map('intval', $matches[1]);
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function getRecordedReminders(): array
    {
        return Repo::eventLog()
            ->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION)
            ->getMany()
            ->filter(
                fn ($entry) => $entry->getId() > $this->lastEventLogIdBeforeTest
                    && $entry->getData('eventType') == PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO
            )
            ->values()
            ->all();
    }

    public function testShouldNotSendRemindersFromContextWherePluginIsDisabled(): void
    {
        $this->disablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        Mail::assertNotSent(PendingReviewsReminder::class);
    }

    public function testShouldNotSendWeeklyReminderFromContextWhereItIsDisabledInSettings(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->setWeeklyReminderEnabled(self::FIRST_CONTEXT_ID, false);

        $this->runTask();

        Mail::assertNotSent(PendingReviewsReminder::class);
        $this->assertEmpty($this->getRecordedReminders());
    }

    public function testShouldSendWeeklyReminderWhenSettingWasNeverSaved(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->setWeeklyReminderEnabled(self::FIRST_CONTEXT_ID, null);

        $this->runTask();

        Mail::assertSent(PendingReviewsReminder::class);
    }

    public function testShouldNotRemindAgainInTheSameWeek(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->setLastWeeklyReminder(self::FIRST_CONTEXT_ID, self::MONDAY_MIDNIGHT);
        Carbon::setTestNow(self::WEDNESDAY_AFTERNOON);

        $this->runTask();

        Mail::assertNotSent(PendingReviewsReminder::class);
    }

    public function testShouldRemindOnTheFirstRunOfTheWeekAfterThePreviousReminder(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->setLastWeeklyReminder(self::FIRST_CONTEXT_ID, self::PREVIOUS_MONDAY_MIDNIGHT);
        Carbon::setTestNow(self::WEDNESDAY_AFTERNOON);

        $this->runTask();

        Mail::assertSent(PendingReviewsReminder::class);
    }

    public function testShouldWaitForMondayBeforeTheFirstWeeklyReminder(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        Carbon::setTestNow(self::WEDNESDAY_AFTERNOON);

        $this->runTask();

        Mail::assertNotSent(PendingReviewsReminder::class);
    }

    public function testShouldRecordWhenTheWeeklyReminderWasSent(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $this->assertEquals(self::MONDAY_MIDNIGHT, (new ReviewReminderPlugin())->getSetting(self::FIRST_CONTEXT_ID, self::LAST_SENT_SETTING));
    }

    public function testShouldNotRetryTheWeeklyReminderInTheSameWeekAfterSendingFails(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->failMailTransport();

        try {
            $this->runTask();
        } catch (TransportException $exception) {
        }

        $this->assertEquals(self::MONDAY_MIDNIGHT, (new ReviewReminderPlugin())->getSetting(self::FIRST_CONTEXT_ID, self::LAST_SENT_SETTING));
    }

    public function testShouldSendRemindersToReviewersOfContextWherePluginIsEnabled(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $recipients = Mail::sent(PendingReviewsReminder::class)
            ->map(fn (PendingReviewsReminder $email) => $email->to[0]['address'])
            ->sort()
            ->values()
            ->all();
        $expectedRecipients = self::FIRST_CONTEXT_REVIEWERS;
        sort($expectedRecipients);
        $this->assertEquals($expectedRecipients, $recipients);
    }

    public function testShouldNotRemindReviewerOfReviewsFromAnotherContext(): void
    {
        $this->createSecondContextWithSharedReviewer();
        $this->enablePlugin($this->secondContextId);

        $this->runTask();

        Mail::assertNotSent(
            PendingReviewsReminder::class,
            fn (PendingReviewsReminder $email) => $email->from[0]['address'] == self::SECOND_CONTEXT_CONTACT_EMAIL
        );
    }

    public function testShouldRemindOnlyReviewsOfSubmissionsUnderReview(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $this->assertEquals(self::SUBMISSION_IDS_UNDER_REVIEW, $this->getRemindedSubmissionIds());
    }

    public function testShouldNotRemindReviewsOfPublishedSubmissions(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $this->assertEmpty(array_intersect(self::PUBLISHED_SUBMISSION_IDS, $this->getRemindedSubmissionIds()));
    }

    public function testShouldNotRemindReviewsOfSubmissionsPastReviewStage(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $this->assertEmpty(array_intersect(self::SUBMISSION_IDS_PAST_REVIEW_STAGE, $this->getRemindedSubmissionIds()));
    }

    public function testShouldNotRemindReviewsOfDeclinedSubmissions(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->declineSubmission(12);

        $this->runTask();

        $this->assertNotContains(12, $this->getRemindedSubmissionIds());
    }

    public function testShouldNotRemindReviewsFromPreviousReviewRounds(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->startNewReviewRound(7);

        $this->runTask();

        $this->assertNotContains(7, $this->getRemindedSubmissionIds());
    }

    public function testShouldReportSuccessfulExecution(): void
    {
        $this->assertTrue($this->runTask());
    }

    public function testShouldRecordReminderInActivityLogOfEachRemindedReview(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $remindedReviews = Mail::sent(PendingReviewsReminder::class)
            ->flatMap(function (PendingReviewsReminder $email) {
                $reviewerId = Repo::user()->getByEmail($email->to[0]['address'], true)->getId();
                preg_match_all('/submissionId=(\d+)/', $email->viewData['submissionsList'], $matches);
                return array_map(fn ($submissionId) => "$submissionId-$reviewerId", $matches[1]);
            })
            ->sort()
            ->values()
            ->all();
        $recordedReminders = collect($this->getRecordedReminders())
            ->map(fn ($entry) => $entry->getData('assocId') . '-' . $entry->getData('recipientId'))
            ->sort()
            ->values()
            ->all();
        $this->assertNotEmpty($remindedReviews);
        $this->assertEquals($remindedReviews, $recordedReminders);
    }

    public function testShouldRecordReminderForTheRemindedReviewer(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $recordedReminders = $this->getRecordedReminders();
        $this->assertNotEmpty($recordedReminders);
        foreach ($recordedReminders as $entry) {
            $reviewAssignment = Repo::reviewAssignment()->get($entry->getData('reviewAssignmentId'));
            $this->assertEquals($reviewAssignment->getReviewerId(), $entry->getData('recipientId'));
            $this->assertEquals($reviewAssignment->getSubmissionId(), $entry->getData('assocId'));
            $this->assertEquals(self::PENDING_REVIEWS_REMINDER_MESSAGE, $entry->getData('message'));
        }
    }

    public function testShouldDescribeRecordedReminderAsSentByThePlugin(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $recordedReminders = $this->getRecordedReminders();
        $this->assertNotEmpty($recordedReminders);
        foreach ($recordedReminders as $entry) {
            $this->assertEquals(
                'The Review Reminder plugin sent the weekly pending reviews reminder to ' . $entry->getData('recipientName'),
                $entry->getTranslatedMessage('en')
            );
        }
    }

    public function testShouldNotChangeReviewReminderDateUsedByCoreReminders(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $reminderDatesBeforeTask = DB::table('review_assignments')->pluck('date_reminded', 'review_id')->all();

        $this->runTask();

        $this->assertNotEmpty($this->getRecordedReminders());
        $this->assertEquals($reminderDatesBeforeTask, DB::table('review_assignments')->pluck('date_reminded', 'review_id')->all());
    }

    public function testShouldNotRecordReminderWhenEmailIsNotSent(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);
        $this->failMailTransport();

        try {
            $this->runTask();
        } catch (TransportException $exception) {
        }

        $this->assertEmpty($this->getRecordedReminders());
    }

    public function testShouldNameTheJournalInTheWeeklyReminderSubject(): void
    {
        $this->enablePlugin(self::FIRST_CONTEXT_ID);

        $this->runTask();

        $email = Mail::sent(PendingReviewsReminder::class)->first();
        $this->assertEquals(self::FIRST_CONTEXT_REMINDER_SUBJECT, app('mailer')->compileParams($email->subject, $email->viewData));
    }
}

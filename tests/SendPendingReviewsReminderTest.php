<?php

use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;
use APP\plugins\generic\reviewReminder\classes\tasks\SendPendingReviewsReminder;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
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

    private $pluginSettingsDao;
    private $secondContextId;
    private $secondContextReviewerGroupId;
    private $declinedSubmissionId;
    private $newReviewRoundId;
    private $lastEventLogIdBeforeTest;
    private $enabledSettingBeforeTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->enabledSettingBeforeTest = $this->getEnabledSetting(self::FIRST_CONTEXT_ID);
        $this->lastEventLogIdBeforeTest = DB::table('event_log')->max('log_id');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->restoreEnabledSetting(self::FIRST_CONTEXT_ID, $this->enabledSettingBeforeTest);

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

    private function getEnabledSetting(int $contextId): ?object
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', $contextId)
            ->where('setting_name', 'enabled')
            ->first();
    }

    private function restoreEnabledSetting(int $contextId, ?object $enabledSetting): void
    {
        if ($enabledSetting) {
            $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, 'enabled', (bool) $enabledSetting->setting_value, 'bool');
            return;
        }

        $this->pluginSettingsDao->deleteSetting($contextId, self::PLUGIN_NAME, 'enabled');
    }

    private function enablePlugin(int $contextId): void
    {
        $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, 'enabled', true, 'bool');
    }

    private function disablePlugin(int $contextId): void
    {
        $this->pluginSettingsDao->updateSetting($contextId, self::PLUGIN_NAME, 'enabled', false, 'bool');
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
            $this->assertEquals('submission.event.reviewer.reviewerRemindedAuto', $entry->getData('message'));
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
        Mail::shouldReceive('send')->andThrow(new TransportException('Connection refused'));

        try {
            $this->runTask();
        } catch (TransportException $exception) {
        }

        $this->assertEmpty($this->getRecordedReminders());
    }
}

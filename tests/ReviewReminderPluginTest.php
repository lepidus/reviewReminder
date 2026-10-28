<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;
use APP\plugins\generic\reviewReminder\classes\tasks\SendPendingReviewsReminder;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\scheduler\Scheduler;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class ReviewReminderPluginTest extends PKPTestCase
{
    private const PLUGIN_NAME = 'reviewreminderplugin';
    private const PLUGIN_PATH = 'plugins/generic/reviewReminder';
    private const CONTEXT_ID = 1;
    private const CONTEXT_PATH = 'publicknowledge';
    private const WEEKLY_REMINDER_SETTING = 'pendingReviewsReminderEnabled';
    private const JOURNAL_SETTINGS_TABLE = 'journal_settings';
    private const LAST_SENT_SETTING = 'pendingReviewsReminderLastSentAt';
    private const RESTORED_SETTINGS = ['enabled', self::LAST_SENT_SETTING];
    private const PREVIOUS_MONDAY_MIDNIGHT = '2026-10-12 00:00:00';
    private const MONDAY_MIDNIGHT = '2026-10-19 00:00:00';
    private const MONDAY_MORNING = '2026-10-19 09:30:00';
    private const WEDNESDAY_AFTERNOON = '2026-10-21 15:00:00';

    private $pluginSettingsDao;
    private $pluginSettingsBeforeTest;
    private $weeklyReminderSettingBeforeTest;
    private $mailableBuildHooksBeforeTest;
    private $mailerMailablesHooksBeforeTest;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->pluginSettingsBeforeTest = DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', self::CONTEXT_ID)
            ->whereIn('setting_name', self::RESTORED_SETTINGS)
            ->get()
            ->keyBy('setting_name');
        $this->weeklyReminderSettingBeforeTest = $this->getWeeklyReminderJournalSetting()->first();
        $hooks = &Hook::getHooks();
        $this->mailableBuildHooksBeforeTest = $hooks['Mailable::build'] ?? null;
        unset($hooks['Mailable::build']);
        $this->mailerMailablesHooksBeforeTest = $hooks['Mailer::Mailables'] ?? null;
        unset($hooks['Mailer::Mailables']);
        $this->createRoutedRequest();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (self::RESTORED_SETTINGS as $settingName) {
            $this->restorePluginSetting($settingName);
        }
        $this->getWeeklyReminderJournalSetting()->delete();
        if ($this->weeklyReminderSettingBeforeTest) {
            DB::table(self::JOURNAL_SETTINGS_TABLE)->insert((array) $this->weeklyReminderSettingBeforeTest);
        }
        $hooks = &Hook::getHooks();
        unset($hooks['Mailable::build'], $hooks['Mailer::Mailables']);
        if ($this->mailableBuildHooksBeforeTest !== null) {
            $hooks['Mailable::build'] = $this->mailableBuildHooksBeforeTest;
        }
        if ($this->mailerMailablesHooksBeforeTest !== null) {
            $hooks['Mailer::Mailables'] = $this->mailerMailablesHooksBeforeTest;
        }
        parent::tearDown();
    }

    private function getWeeklyReminderJournalSetting()
    {
        return DB::table(self::JOURNAL_SETTINGS_TABLE)
            ->where('journal_id', self::CONTEXT_ID)
            ->where('setting_name', self::WEEKLY_REMINDER_SETTING);
    }

    private function turnOffWeeklyReminderInJournal(): void
    {
        $this->getWeeklyReminderJournalSetting()->delete();
        DB::table(self::JOURNAL_SETTINGS_TABLE)->insert([
            'journal_id' => self::CONTEXT_ID,
            'locale' => '',
            'setting_name' => self::WEEKLY_REMINDER_SETTING,
            'setting_value' => '0',
        ]);
    }

    private function createRoutedRequest(): void
    {
        Registry::delete('request');
        $_SERVER['PATH_INFO'] = self::CONTEXT_PATH . '/index/index';
        $application = Application::get();
        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $application->getRequest()->setRouter($router);
    }

    private function restorePluginSetting(string $settingName): void
    {
        $setting = $this->pluginSettingsBeforeTest->get($settingName);
        if ($setting) {
            $value = $setting->setting_type === 'bool' ? (bool) $setting->setting_value : $setting->setting_value;
            $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, $settingName, $value, $setting->setting_type);
            return;
        }

        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, $settingName);
    }

    private function registerPlugin(bool $isEnabled): ReviewReminderPlugin
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', $isEnabled, 'bool');
        $plugin = new ReviewReminderPlugin();
        $plugin->register('generic', self::PLUGIN_PATH, self::CONTEXT_ID);

        return $plugin;
    }

    private function getJournalEmails(): array
    {
        $context = app()->get('context')->get(self::CONTEXT_ID);
        return Repo::mailable()->getMany($context)->values()->all();
    }

    private function setLastPendingReviewsReminder(?string $dateTime): void
    {
        if ($dateTime === null) {
            $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::LAST_SENT_SETTING);
            return;
        }

        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::LAST_SENT_SETTING, $dateTime, 'string');
    }

    private function isPendingReviewsReminderScheduledToRunAt(string $dateTime): bool
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', true, 'bool');
        Carbon::setTestNow($dateTime);
        $schedule = new Schedule();
        (new ReviewReminderPlugin())->registerSchedules(new Scheduler($schedule));

        $pendingReviewsReminders = collect($schedule->events())
            ->filter(fn (Event $event) => $event->getSummaryForDisplay() == SendPendingReviewsReminder::class)
            ->values();
        $this->assertCount(1, $pendingReviewsReminders);

        return $pendingReviewsReminders[0]->isDue(app()) && $pendingReviewsReminders[0]->filtersPass(app());
    }

    public function testShouldRunPendingReviewsReminderAtMondayMidnight(): void
    {
        $this->setLastPendingReviewsReminder(self::PREVIOUS_MONDAY_MIDNIGHT);

        $this->assertTrue($this->isPendingReviewsReminderScheduledToRunAt(self::MONDAY_MIDNIGHT));
    }

    public function testShouldRunPendingReviewsReminderLaterInTheWeekWhenMondayMidnightWasMissed(): void
    {
        $this->setLastPendingReviewsReminder(self::PREVIOUS_MONDAY_MIDNIGHT);

        $this->assertTrue($this->isPendingReviewsReminderScheduledToRunAt(self::MONDAY_MORNING));
    }

    public function testShouldNotRunPendingReviewsReminderAgainInTheSameWeek(): void
    {
        $this->setLastPendingReviewsReminder(self::MONDAY_MIDNIGHT);

        $this->assertFalse($this->isPendingReviewsReminderScheduledToRunAt(self::WEDNESDAY_AFTERNOON));
    }

    public function testShouldWaitForMondayBeforeTheFirstPendingReviewsReminder(): void
    {
        $this->setLastPendingReviewsReminder(null);

        $this->assertFalse($this->isPendingReviewsReminderScheduledToRunAt(self::WEDNESDAY_AFTERNOON));
    }

    public function testShouldKeepAttachingCalendarInviteWhenWeeklyReminderIsDisabled(): void
    {
        $this->turnOffWeeklyReminderInJournal();

        $this->registerPlugin(true);

        $mailableBuildCallbacks = array_merge(...array_values(Hook::getHooks('Mailable::build') ?? []));
        $this->assertContains('attachCalendarInvite', array_map(fn ($callback) => $callback[1] ?? null, $mailableBuildCallbacks));
    }

    public function testShouldListWeeklyReminderAmongJournalEmails(): void
    {
        $this->registerPlugin(true);

        $this->assertContains(PendingReviewsReminder::class, $this->getJournalEmails());
    }

    public function testShouldNotListWeeklyReminderAmongJournalEmailsWhenPluginIsDisabled(): void
    {
        $this->registerPlugin(false);

        $this->assertNotContains(PendingReviewsReminder::class, $this->getJournalEmails());
    }
}

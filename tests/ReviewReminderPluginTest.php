<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\ReviewReminder;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
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
    private const SITE_PATH = 'index';
    private const WEEKLY_REMINDER_SETTING = 'pendingReviewsReminderEnabled';

    private $pluginSettingsDao;
    private $enabledSettingBeforeTest;
    private $weeklyReminderSettingBeforeTest;
    private $confirmReviewHooksBeforeTest;
    private $mailerMailablesHooksBeforeTest;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRoutedRequest(self::SITE_PATH);
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->enabledSettingBeforeTest = DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', self::CONTEXT_ID)
            ->where('setting_name', 'enabled')
            ->first();
        $this->weeklyReminderSettingBeforeTest = $this->getWeeklyReminderJournalSetting()->first();
        $hooks = &Hook::getHooks();
        $this->confirmReviewHooksBeforeTest = $hooks['ReviewerAction::confirmReview'] ?? null;
        unset($hooks['ReviewerAction::confirmReview']);
        $this->mailerMailablesHooksBeforeTest = $hooks['Mailer::Mailables'] ?? null;
        unset($hooks['Mailer::Mailables']);
    }

    protected function tearDown(): void
    {
        $this->restoreEnabledSetting();
        $this->restoreWeeklyReminderSetting();
        $hooks = &Hook::getHooks();
        unset($hooks['ReviewerAction::confirmReview'], $hooks['Mailer::Mailables']);
        if ($this->confirmReviewHooksBeforeTest !== null) {
            $hooks['ReviewerAction::confirmReview'] = $this->confirmReviewHooksBeforeTest;
        }
        if ($this->mailerMailablesHooksBeforeTest !== null) {
            $hooks['Mailer::Mailables'] = $this->mailerMailablesHooksBeforeTest;
        }
        parent::tearDown();
    }

    private function createRoutedRequest(string $contextPath): void
    {
        Registry::delete('request');
        $_SERVER['PATH_INFO'] = $contextPath . '/index/index';
        $application = Application::get();
        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $application->getRequest()->setRouter($router);
    }

    private function restoreEnabledSetting(): void
    {
        if ($this->enabledSettingBeforeTest) {
            $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', (bool) $this->enabledSettingBeforeTest->setting_value, 'bool');
            return;
        }

        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled');
    }

    private function getWeeklyReminderJournalSetting()
    {
        return DB::table('journal_settings')
            ->where('journal_id', self::CONTEXT_ID)
            ->where('setting_name', self::WEEKLY_REMINDER_SETTING);
    }

    private function restoreWeeklyReminderSetting(): void
    {
        $this->getWeeklyReminderJournalSetting()->delete();
        if ($this->weeklyReminderSettingBeforeTest) {
            DB::table('journal_settings')->insert((array) $this->weeklyReminderSettingBeforeTest);
        }
    }

    private function turnOffWeeklyReminderInJournal(): void
    {
        $this->getWeeklyReminderJournalSetting()->delete();
        DB::table('journal_settings')->insert([
            'journal_id' => self::CONTEXT_ID,
            'locale' => '',
            'setting_name' => self::WEEKLY_REMINDER_SETTING,
            'setting_value' => '0',
        ]);
    }

    private function registerPluginInJournal(bool $isEnabled): ReviewReminderPlugin
    {
        $this->createRoutedRequest(self::CONTEXT_PATH);
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', $isEnabled, 'bool');
        $plugin = new ReviewReminderPlugin();
        $plugin->register('generic', self::PLUGIN_PATH, self::CONTEXT_ID);

        return $plugin;
    }

    private function getJournalEmails(): array
    {
        $context = Application::getContextDAO()->getById(self::CONTEXT_ID);
        return Repo::mailable()->getMany($context)->values()->all();
    }

    private function getCrontabTaskFiles(): array
    {
        $plugin = new ReviewReminderPlugin();
        $plugin->register('generic', self::PLUGIN_PATH);
        $this->assertFalse((bool) $plugin->getEnabled());

        $taskFilesPath = [];
        Hook::call('AcronPlugin::parseCronTab', [&$taskFilesPath]);
        return $taskFilesPath;
    }

    public function testShouldAddTasksToCrontabEvenIfDisabledInCurrentContext(): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', true, 'bool');

        $taskFilesPath = $this->getCrontabTaskFiles();

        $this->assertContains(self::PLUGIN_PATH . DIRECTORY_SEPARATOR . 'scheduledTasks.xml', $taskFilesPath);
    }

    public function testShouldNotAddTasksToCrontabWhenDisabledInAllContexts(): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', false, 'bool');

        $taskFilesPath = $this->getCrontabTaskFiles();

        $this->assertNotContains(self::PLUGIN_PATH . DIRECTORY_SEPARATOR . 'scheduledTasks.xml', $taskFilesPath);
    }

    public function testShouldKeepSendingReminderOnReviewAcceptanceWhenWeeklyReminderIsDisabled(): void
    {
        $this->turnOffWeeklyReminderInJournal();

        $this->registerPluginInJournal(true);

        $confirmReviewCallbacks = array_merge(...array_values(Hook::getHooks('ReviewerAction::confirmReview') ?? []));
        $this->assertContains('sendReviewReminder', array_map(fn ($callback) => $callback[1] ?? null, $confirmReviewCallbacks));
    }

    public function testShouldListWeeklyReminderAmongJournalEmails(): void
    {
        $this->registerPluginInJournal(true);

        $this->assertContains(PendingReviewsReminder::class, $this->getJournalEmails());
    }

    public function testShouldNotListWeeklyReminderAmongJournalEmailsWhenPluginIsDisabled(): void
    {
        $this->registerPluginInJournal(false);

        $this->assertNotContains(PendingReviewsReminder::class, $this->getJournalEmails());
    }

    public function testShouldListAcceptanceReminderAmongJournalEmails(): void
    {
        $this->registerPluginInJournal(true);

        $this->assertContains(ReviewReminder::class, $this->getJournalEmails());
    }
}

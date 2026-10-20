<?php

use APP\core\Application;
use APP\core\PageRouter;
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
        $this->weeklyReminderSettingBeforeTest = DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', self::CONTEXT_ID)
            ->where('setting_name', self::WEEKLY_REMINDER_SETTING)
            ->first();
        $hooks = &Hook::getHooks();
        $this->confirmReviewHooksBeforeTest = $hooks['ReviewerAction::confirmReview'] ?? null;
        unset($hooks['ReviewerAction::confirmReview']);
    }

    protected function tearDown(): void
    {
        $this->restoreEnabledSetting();
        $this->restoreWeeklyReminderSetting();
        $hooks = &Hook::getHooks();
        $hooks['ReviewerAction::confirmReview'] = $this->confirmReviewHooksBeforeTest;
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

    private function restoreWeeklyReminderSetting(): void
    {
        if ($this->weeklyReminderSettingBeforeTest) {
            $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING, (bool) $this->weeklyReminderSettingBeforeTest->setting_value, 'bool');
            return;
        }

        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING);
    }

    private function registerPluginInJournal(bool $isEnabled): ReviewReminderPlugin
    {
        $this->createRoutedRequest(self::CONTEXT_PATH);
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', $isEnabled, 'bool');
        $plugin = new ReviewReminderPlugin();
        $plugin->register('generic', self::PLUGIN_PATH, self::CONTEXT_ID);

        return $plugin;
    }

    private function getActionIds(ReviewReminderPlugin $plugin): array
    {
        return array_map(
            fn ($action) => $action->getId(),
            $plugin->getActions(Application::get()->getRequest(), [])
        );
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

    public function testShouldOfferSettingsWhenPluginIsEnabled(): void
    {
        $plugin = $this->registerPluginInJournal(true);

        $this->assertContains('settings', $this->getActionIds($plugin));
    }

    public function testShouldNotOfferSettingsWhenPluginIsDisabled(): void
    {
        $plugin = $this->registerPluginInJournal(false);

        $this->assertNotContains('settings', $this->getActionIds($plugin));
    }

    public function testShouldKeepSendingReminderOnReviewAcceptanceWhenWeeklyReminderIsDisabled(): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING, false, 'bool');

        $this->registerPluginInJournal(true);

        $confirmReviewCallbacks = array_merge(...array_values(Hook::getHooks('ReviewerAction::confirmReview') ?? []));
        $this->assertContains('sendReviewReminder', array_map(fn ($callback) => $callback[1] ?? null, $confirmReviewCallbacks));
    }
}

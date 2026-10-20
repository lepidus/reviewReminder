<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderSettingsForm;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use Illuminate\Support\Facades\DB;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;

class ReviewReminderSettingsFormTest extends PKPTestCase
{
    private const PLUGIN_NAME = 'reviewreminderplugin';
    private const PLUGIN_PATH = 'plugins/generic/reviewReminder';
    private const CONTEXT_ID = 1;
    private const CONTEXT_PATH = 'publicknowledge';
    private const WEEKLY_REMINDER_SETTING = 'pendingReviewsReminderEnabled';

    private $pluginSettingsDao;
    private $weeklyReminderSettingBeforeTest;
    private $plugin;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->weeklyReminderSettingBeforeTest = DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', self::CONTEXT_ID)
            ->where('setting_name', self::WEEKLY_REMINDER_SETTING)
            ->first();
        $this->createRoutedRequest();
        $this->plugin = new ReviewReminderPlugin();
        $this->plugin->register('generic', self::PLUGIN_PATH, self::CONTEXT_ID);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $this->restoreWeeklyReminderSetting();
        parent::tearDown();
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

    private function restoreWeeklyReminderSetting(): void
    {
        if ($this->weeklyReminderSettingBeforeTest) {
            $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING, (bool) $this->weeklyReminderSettingBeforeTest->setting_value, 'bool');
            return;
        }

        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING);
    }

    private function submitForm(array $userVars): void
    {
        $_POST = $userVars;
        Application::get()->getRequest()->_requestVars = null;

        $form = new ReviewReminderSettingsForm($this->plugin, self::CONTEXT_ID);
        $form->readInputData();
        $form->execute();
    }

    private function getInitialWeeklyReminderValue(): mixed
    {
        $form = new ReviewReminderSettingsForm($this->plugin, self::CONTEXT_ID);
        $form->initData();

        return $form->getData(self::WEEKLY_REMINDER_SETTING);
    }

    public function testShouldShowWeeklyReminderEnabledWhenSettingWasNeverSaved(): void
    {
        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING);

        $this->assertTrue($this->getInitialWeeklyReminderValue());
    }

    public function testShouldShowWeeklyReminderDisabledAfterItWasDisabled(): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING, false, 'bool');

        $this->assertFalse($this->getInitialWeeklyReminderValue());
    }

    public function testShouldDisableWeeklyReminderWhenCheckboxIsUnchecked(): void
    {
        $this->submitForm([]);

        $this->assertFalse($this->plugin->getSetting(self::CONTEXT_ID, self::WEEKLY_REMINDER_SETTING));
    }

    public function testShouldEnableWeeklyReminderWhenCheckboxIsChecked(): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING, false, 'bool');

        $this->submitForm([self::WEEKLY_REMINDER_SETTING => '1']);

        $this->assertTrue($this->plugin->getSetting(self::CONTEXT_ID, self::WEEKLY_REMINDER_SETTING));
    }
}

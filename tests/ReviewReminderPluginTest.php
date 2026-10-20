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

    private $pluginSettingsDao;
    private $enabledSettingBeforeTest;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRoutedRequest();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->enabledSettingBeforeTest = DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', self::CONTEXT_ID)
            ->where('setting_name', 'enabled')
            ->first();
    }

    protected function tearDown(): void
    {
        $this->restoreEnabledSetting();
        parent::tearDown();
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

    private function restoreEnabledSetting(): void
    {
        if ($this->enabledSettingBeforeTest) {
            $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', (bool) $this->enabledSettingBeforeTest->setting_value, 'bool');
            return;
        }

        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled');
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
}

<?php

use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class ReviewReminderPluginTest extends PKPTestCase
{
    private const PLUGIN_NAME = 'reviewreminderplugin';
    private const PLUGIN_PATH = 'plugins/generic/reviewReminder';
    private const CONTEXT_ID = 1;

    private $pluginSettingsDao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
    }

    protected function tearDown(): void
    {
        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled');
        parent::tearDown();
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

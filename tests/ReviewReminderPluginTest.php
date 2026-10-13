<?php

use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class ReviewReminderPluginTest extends PKPTestCase
{
    private const PLUGIN_PATH = 'plugins/generic/reviewReminder';

    public function testShouldAddTasksToCrontabEvenIfDisabledInCurrentContext(): void
    {
        $plugin = new ReviewReminderPlugin();
        $plugin->register('generic', self::PLUGIN_PATH);
        $this->assertFalse((bool) $plugin->getEnabled());

        $taskFilesPath = [];
        Hook::call('AcronPlugin::parseCronTab', [&$taskFilesPath]);

        $this->assertContains(self::PLUGIN_PATH . DIRECTORY_SEPARATOR . 'scheduledTasks.xml', $taskFilesPath);
    }
}

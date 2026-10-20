<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\reviewReminder\classes\tasks\SendPendingReviewsReminder;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\scheduler\Scheduler;
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
    private const WEEKLY_ON_MONDAY = '0 0 * * 1';
    private const PLUGIN_NAME = 'reviewreminderplugin';
    private const PLUGIN_PATH = 'plugins/generic/reviewReminder';
    private const CONTEXT_ID = 1;
    private const CONTEXT_PATH = 'publicknowledge';
    private const WEEKLY_REMINDER_SETTING = 'pendingReviewsReminderEnabled';

    private $pluginSettingsDao;
    private $pluginSettingsBeforeTest;
    private $mailableBuildHooksBeforeTest;

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
            ->whereIn('setting_name', ['enabled', self::WEEKLY_REMINDER_SETTING])
            ->get()
            ->keyBy('setting_name');
        $hooks = &Hook::getHooks();
        $this->mailableBuildHooksBeforeTest = $hooks['Mailable::build'] ?? null;
        unset($hooks['Mailable::build']);
        $this->createRoutedRequest();
    }

    protected function tearDown(): void
    {
        foreach (['enabled', self::WEEKLY_REMINDER_SETTING] as $settingName) {
            $this->restorePluginSetting($settingName);
        }
        $hooks = &Hook::getHooks();
        $hooks['Mailable::build'] = $this->mailableBuildHooksBeforeTest;
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

    private function restorePluginSetting(string $settingName): void
    {
        $setting = $this->pluginSettingsBeforeTest->get($settingName);
        if ($setting) {
            $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, $settingName, (bool) $setting->setting_value, 'bool');
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

    private function getActionIds(ReviewReminderPlugin $plugin): array
    {
        return array_map(
            fn ($action) => $action->getId(),
            $plugin->getActions(Application::get()->getRequest(), [])
        );
    }

    public function testShouldScheduleWeeklyPendingReviewsReminder(): void
    {
        $schedule = new Schedule();

        (new ReviewReminderPlugin())->registerSchedules(new Scheduler($schedule));

        $pendingReviewsReminders = collect($schedule->events())
            ->filter(fn (Event $event) => $event->getSummaryForDisplay() == SendPendingReviewsReminder::class)
            ->values();
        $this->assertCount(1, $pendingReviewsReminders);
        $this->assertEquals(self::WEEKLY_ON_MONDAY, $pendingReviewsReminders[0]->expression);
    }

    public function testShouldOfferSettingsWhenPluginIsEnabled(): void
    {
        $plugin = $this->registerPlugin(true);

        $this->assertContains('settings', $this->getActionIds($plugin));
    }

    public function testShouldNotOfferSettingsWhenPluginIsDisabled(): void
    {
        $plugin = $this->registerPlugin(false);

        $this->assertNotContains('settings', $this->getActionIds($plugin));
    }

    public function testShouldKeepAttachingCalendarInviteWhenWeeklyReminderIsDisabled(): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, self::WEEKLY_REMINDER_SETTING, false, 'bool');

        $this->registerPlugin(true);

        $mailableBuildCallbacks = array_merge(...array_values(Hook::getHooks('Mailable::build') ?? []));
        $this->assertContains('attachCalendarInvite', array_map(fn ($callback) => $callback[1] ?? null, $mailableBuildCallbacks));
    }
}

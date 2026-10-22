<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Services;
use APP\plugins\generic\reviewReminder\classes\PendingReviewsReminderSetting;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use Illuminate\Support\Facades\DB;
use PKP\components\forms\context\PKPReviewSetupForm;
use PKP\components\forms\FormComponent;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class PendingReviewsReminderSettingTest extends PKPTestCase
{
    private const PLUGIN_NAME = 'reviewreminderplugin';
    private const PLUGIN_PATH = 'plugins/generic/reviewReminder';
    private const CONTEXT_ID = 1;
    private const SETTING_NAME = 'pendingReviewsReminderEnabled';
    private const EXTENDED_HOOKS = ['Schema::get::before::context', 'Schema::get::context', 'Form::config::before'];

    private $pluginSettingsDao;
    private $enabledSettingBeforeTest;
    private $journalSettingBeforeTest;
    private $hooksBeforeTest = [];

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRoutedRequest();
        Locale::registerPath('plugins/generic/reviewReminder/locale');
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->enabledSettingBeforeTest = $this->pluginSettingsDao->getSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled');
        $this->journalSettingBeforeTest = $this->getJournalSettingRow();
        $hooks = &Hook::getHooks();
        foreach (self::EXTENDED_HOOKS as $hookName) {
            $this->hooksBeforeTest[$hookName] = $hooks[$hookName] ?? null;
            unset($hooks[$hookName]);
        }
    }

    protected function tearDown(): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', (bool) $this->enabledSettingBeforeTest, 'bool');
        DB::table('journal_settings')->where('journal_id', self::CONTEXT_ID)->where('setting_name', self::SETTING_NAME)->delete();
        if ($this->journalSettingBeforeTest) {
            DB::table('journal_settings')->insert((array) $this->journalSettingBeforeTest);
        }
        $hooks = &Hook::getHooks();
        foreach ($this->hooksBeforeTest as $hookName => $callbacks) {
            unset($hooks[$hookName]);
            if ($callbacks !== null) {
                $hooks[$hookName] = $callbacks;
            }
        }
        Services::get('schema')->get('context', true);
        parent::tearDown();
    }

    private function createRoutedRequest(): void
    {
        Registry::delete('request');
        $_SERVER['PATH_INFO'] = 'publicknowledge/index/index';
        $application = Application::get();
        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $application->getRequest()->setRouter($router);
    }

    private function getJournalSettingRow(): ?object
    {
        return DB::table('journal_settings')
            ->where('journal_id', self::CONTEXT_ID)
            ->where('setting_name', self::SETTING_NAME)
            ->first();
    }

    private function saveJournalSetting(bool $isEnabled): void
    {
        DB::table('journal_settings')->where('journal_id', self::CONTEXT_ID)->where('setting_name', self::SETTING_NAME)->delete();
        DB::table('journal_settings')->insert([
            'journal_id' => self::CONTEXT_ID,
            'locale' => '',
            'setting_name' => self::SETTING_NAME,
            'setting_value' => $isEnabled ? '1' : '0',
        ]);
    }

    private function registerPlugin(bool $isEnabled): void
    {
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', $isEnabled, 'bool');
        (new ReviewReminderPlugin())->register('generic', self::PLUGIN_PATH, self::CONTEXT_ID);
    }

    private function getReviewSetupFields(): array
    {
        $context = Application::getContextDAO()->getById(self::CONTEXT_ID);
        $form = new PKPReviewSetupForm('http://localhost/api/v1/contexts/1', [], $context);
        Hook::run('Form::config::before', [$form]);

        return array_map(fn ($field) => $field->getConfig(), array_values($form->fields));
    }

    private function getFieldIndex(array $fields, string $fieldName): ?int
    {
        foreach ($fields as $index => $field) {
            if ($field['name'] === $fieldName) {
                return $index;
            }
        }

        return null;
    }

    public function testShouldConsiderWeeklyReminderEnabledWhenNeverSaved(): void
    {
        DB::table('journal_settings')->where('journal_id', self::CONTEXT_ID)->where('setting_name', self::SETTING_NAME)->delete();

        $this->assertTrue(PendingReviewsReminderSetting::isEnabled(self::CONTEXT_ID));
    }

    public function testShouldReadWeeklyReminderTurnedOffInJournalSettings(): void
    {
        $this->saveJournalSetting(false);

        $this->assertFalse(PendingReviewsReminderSetting::isEnabled(self::CONTEXT_ID));
    }

    public function testShouldAddSettingToJournalSchemaLoadedBeforeThePlugin(): void
    {
        Services::get('schema')->get('context', true);

        $this->registerPlugin(true);

        $this->assertTrue(property_exists(Services::get('schema')->get('context')->properties, self::SETTING_NAME));
    }

    public function testShouldSaveSettingWithTheJournalSettings(): void
    {
        $this->registerPlugin(true);
        $contextService = Services::get('context');
        $context = $contextService->get(self::CONTEXT_ID);

        $contextService->edit($context, [self::SETTING_NAME => false], Application::get()->getRequest());

        $this->assertSame('0', $this->getJournalSettingRow()?->setting_value);
        $this->assertFalse(PendingReviewsReminderSetting::isEnabled(self::CONTEXT_ID));
    }

    public function testShouldOfferWeeklyReminderAtTheEndOfReviewSetup(): void
    {
        $this->registerPlugin(true);

        $fields = $this->getReviewSetupFields();

        $settingIndex = $this->getFieldIndex($fields, self::SETTING_NAME);
        $this->assertSame(count($fields) - 1, $settingIndex);
        $this->assertSame('Send the weekly pending reviews reminder', $fields[$settingIndex]['options'][0]['label']);
        $this->assertTrue($fields[$settingIndex]['value']);
    }

    public function testShouldShowWeeklyReminderTurnedOffInReviewSetup(): void
    {
        $this->saveJournalSetting(false);
        $this->registerPlugin(true);

        $fields = $this->getReviewSetupFields();

        $this->assertFalse($fields[$this->getFieldIndex($fields, self::SETTING_NAME)]['value']);
    }

    public function testShouldNotOfferWeeklyReminderInReviewSetupWhenPluginIsDisabled(): void
    {
        $this->registerPlugin(false);

        $this->assertNull($this->getFieldIndex($this->getReviewSetupFields(), self::SETTING_NAME));
    }

    public function testShouldNotChangeOtherForms(): void
    {
        $this->registerPlugin(true);
        $form = new FormComponent('otherForm', 'PUT', 'http://localhost', []);

        Hook::run('Form::config::before', [$form]);

        $this->assertEmpty($form->fields);
    }
}

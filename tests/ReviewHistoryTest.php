<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\template\TemplateManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PKP\controllers\grid\users\reviewer\PKPReviewerGridHandler;
use PKP\core\Dispatcher;
use PKP\core\PKPApplication;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class ReviewHistoryTest extends PKPTestCase
{
    private const PLUGIN_NAME = 'reviewreminderplugin';
    private const PLUGIN_PATH = 'plugins/generic/reviewReminder';
    private const CONTEXT_ID = 1;
    private const CONTEXT_PATH = 'publicknowledge';
    private const SUBMISSION_ID = 7;
    private const EXISTING_REVIEW_ASSIGNMENT_ID = 11;
    private const FIRST_REMINDER_DATE = '2026-10-05 08:30:00';
    private const SECOND_REMINDER_DATE = '2026-10-12 14:45:00';
    private const CORE_REVIEW_HISTORY_TEMPLATE = 'workflow/reviewHistory.tpl';
    private const PENDING_REVIEWS_REMINDER_MESSAGE = 'plugins.generic.reviewReminder.event.pendingReviewsReminderSent';
    private const CORE_AUTOMATIC_REMINDER_MESSAGE = 'submission.event.reviewer.reviewerRemindedAuto';
    private const PENDING_REVIEWS_REMINDER_LABEL = 'Weekly pending reviews reminder';
    private const REVIEW_REMINDER_MESSAGE = 'plugins.generic.reviewReminder.event.reviewReminderSent';
    private const REVIEW_REMINDER_LABEL = 'Reminder with calendar invitation';

    private $pluginSettingsDao;
    private $lastEventLogIdBeforeTest;
    private $templateManager;
    private $router;
    private $fetchHooksBeforeTest;
    private $enabledSettingBeforeTest;
    private $reviewAssignmentId;
    private $otherReviewAssignmentId;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->enabledSettingBeforeTest = DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', self::CONTEXT_ID)
            ->where('setting_name', 'enabled')
            ->first();
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', true, 'bool');
        $this->lastEventLogIdBeforeTest = DB::table('event_log')->max('log_id');

        $hooks = &Hook::getHooks();
        $this->fetchHooksBeforeTest = $hooks['TemplateManager::fetch'] ?? null;
        $request = $this->createReviewHistoryRequest();
        $this->reviewAssignmentId = $this->createReviewAssignment();
        $this->otherReviewAssignmentId = $this->createReviewAssignment();
        $this->authorizeReviewAssignment($this->reviewAssignmentId);
        $plugin = new ReviewReminderPlugin();
        $plugin->register('generic', self::PLUGIN_PATH, self::CONTEXT_ID);
        $this->templateManager = TemplateManager::getManager($request);
    }

    protected function tearDown(): void
    {
        $createdEventLogIds = DB::table('event_log')->where('log_id', '>', $this->lastEventLogIdBeforeTest)->pluck('log_id');
        DB::table('event_log_settings')->whereIn('log_id', $createdEventLogIds)->delete();
        DB::table('event_log')->whereIn('log_id', $createdEventLogIds)->delete();
        DB::table('review_assignments')->whereIn('review_id', [$this->reviewAssignmentId, $this->otherReviewAssignmentId])->delete();

        $this->restoreEnabledSetting();
        $this->setFetchHooks($this->fetchHooksBeforeTest);

        parent::tearDown();
    }

    private function createReviewAssignment(): int
    {
        $reviewAssignment = (array) DB::table('review_assignments')->where('review_id', self::EXISTING_REVIEW_ASSIGNMENT_ID)->first();
        unset($reviewAssignment['review_id']);

        return DB::table('review_assignments')->insertGetId($reviewAssignment, 'review_id');
    }

    private function restoreEnabledSetting(): void
    {
        if ($this->enabledSettingBeforeTest) {
            $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', (bool) $this->enabledSettingBeforeTest->setting_value, 'bool');
            return;
        }

        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled');
    }

    private function createReviewHistoryRequest()
    {
        Registry::delete('request');
        $_SERVER['PATH_INFO'] = self::CONTEXT_PATH . '/index/index';
        $application = Application::get();
        $request = $application->getRequest();

        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $request->setRouter($router);
        $this->router = $router;

        return $request;
    }

    private function authorizeReviewAssignment(?int $reviewAssignmentId): void
    {
        $reviewAssignment = $reviewAssignmentId
            ? DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewAssignmentId)
            : null;
        $this->router->setHandler(new class ($reviewAssignment) extends PKPReviewerGridHandler {
            private $reviewAssignment;

            public function __construct($reviewAssignment)
            {
                parent::__construct();
                $this->reviewAssignment = $reviewAssignment;
            }

            public function &getAuthorizedContextObject($assocType)
            {
                $authorizedObject = $assocType == PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT ? $this->reviewAssignment : null;
                return $authorizedObject;
            }
        });
    }

    private function recordReminder(int $reviewAssignmentId, string $dateLogged, string $message = self::PENDING_REVIEWS_REMINDER_MESSAGE): void
    {
        $reviewAssignment = DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewAssignmentId);
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        Repo::eventLog()->add(Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => self::SUBMISSION_ID,
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO,
            'userId' => null,
            'message' => $message,
            'isTranslated' => false,
            'dateLogged' => $dateLogged,
            'recipientId' => $reviewer->getId(),
            'recipientName' => $reviewer->getFullName(),
            'reviewAssignmentId' => $reviewAssignmentId,
        ]));
    }

    private function renderReviewHistory(): string
    {
        $reviewAssignment = DAORegistry::getDAO('ReviewAssignmentDAO')->getById($this->reviewAssignmentId);
        $this->templateManager->assign('dates', [
            'common.assigned' => $reviewAssignment->getDateAssigned(),
            'common.notified' => $reviewAssignment->getDateNotified(),
            'common.reminder' => $reviewAssignment->getDateReminded(),
        ]);

        return $this->templateManager->fetch(self::CORE_REVIEW_HISTORY_TEMPLATE);
    }

    private function setFetchHooks(?array $fetchHooks): void
    {
        $hooks = &Hook::getHooks();
        unset($hooks['TemplateManager::fetch']);
        if ($fetchHooks !== null) {
            $hooks['TemplateManager::fetch'] = $fetchHooks;
        }
    }

    private function renderCoreReviewHistory(): string
    {
        $hooks = &Hook::getHooks();
        $pluginFetchHooks = $hooks['TemplateManager::fetch'] ?? null;
        $this->setFetchHooks($this->fetchHooksBeforeTest);

        try {
            return $this->renderReviewHistory();
        } finally {
            $this->setFetchHooks($pluginFetchHooks);
        }
    }

    private function renderPluginReminders(): string
    {
        $coreReviewHistory = $this->renderCoreReviewHistory();
        $reviewHistory = $this->renderReviewHistory();
        $this->assertStringStartsWith($coreReviewHistory, $reviewHistory);

        return substr($reviewHistory, strlen($coreReviewHistory));
    }

    private function formatHistoryDate(string $date): string
    {
        $format = $this->templateManager->getTemplateVars('datetimeFormatShort');
        return (new Carbon($date))->locale(Locale::getLocale())->translatedFormat($format);
    }

    public function testShouldKeepCoreHistoryUnchangedWhenNoReminderWasSent(): void
    {
        $this->assertSame($this->renderCoreReviewHistory(), $this->renderReviewHistory());
    }

    public function testShouldRenderCoreHistoryBeforeTheRemindersSentByThePlugin(): void
    {
        $this->recordReminder($this->reviewAssignmentId, self::FIRST_REMINDER_DATE);

        $reviewHistory = $this->renderReviewHistory();

        $this->assertStringStartsWith($this->renderCoreReviewHistory(), $reviewHistory);
        $this->assertNotSame($this->renderCoreReviewHistory(), $reviewHistory);
    }

    public function testShouldIdentifyThePluginAsSenderOfTheListedReminders(): void
    {
        $this->recordReminder($this->reviewAssignmentId, self::FIRST_REMINDER_DATE);

        $pluginReminders = $this->renderPluginReminders();

        $this->assertStringContainsString('Review Reminder', $pluginReminders);
        $this->assertStringContainsString(self::PENDING_REVIEWS_REMINDER_LABEL, $pluginReminders);
    }

    public function testShouldListEveryReminderSentByThePlugin(): void
    {
        $this->recordReminder($this->reviewAssignmentId, self::FIRST_REMINDER_DATE);
        $this->recordReminder($this->reviewAssignmentId, self::SECOND_REMINDER_DATE);

        $pluginReminders = $this->renderPluginReminders();

        $this->assertStringContainsString($this->formatHistoryDate(self::FIRST_REMINDER_DATE), $pluginReminders);
        $this->assertStringContainsString($this->formatHistoryDate(self::SECOND_REMINDER_DATE), $pluginReminders);
        $this->assertSame(2, substr_count($pluginReminders, self::PENDING_REVIEWS_REMINDER_LABEL));
    }

    public function testShouldListRemindersSentByThePluginInChronologicalOrder(): void
    {
        $this->recordReminder($this->reviewAssignmentId, self::SECOND_REMINDER_DATE);
        $this->recordReminder($this->reviewAssignmentId, self::FIRST_REMINDER_DATE);

        $pluginReminders = $this->renderPluginReminders();

        $this->assertLessThan(
            strpos($pluginReminders, $this->formatHistoryDate(self::SECOND_REMINDER_DATE)),
            strpos($pluginReminders, $this->formatHistoryDate(self::FIRST_REMINDER_DATE))
        );
    }

    public function testShouldDistinguishReminderSentOnReviewAcceptanceFromWeeklyReminder(): void
    {
        $this->recordReminder($this->reviewAssignmentId, self::FIRST_REMINDER_DATE, self::REVIEW_REMINDER_MESSAGE);
        $this->recordReminder($this->reviewAssignmentId, self::SECOND_REMINDER_DATE);

        $pluginReminders = $this->renderPluginReminders();

        $this->assertSame(1, substr_count($pluginReminders, self::REVIEW_REMINDER_LABEL));
        $this->assertSame(1, substr_count($pluginReminders, self::PENDING_REVIEWS_REMINDER_LABEL));
        $this->assertLessThan(
            strpos($pluginReminders, self::PENDING_REVIEWS_REMINDER_LABEL),
            strpos($pluginReminders, self::REVIEW_REMINDER_LABEL)
        );
    }

    public function testShouldNotListRemindersOfOtherReviewAssignments(): void
    {
        $this->recordReminder($this->otherReviewAssignmentId, self::FIRST_REMINDER_DATE);

        $this->assertSame($this->renderCoreReviewHistory(), $this->renderReviewHistory());
    }

    public function testShouldNotListCoreRemindersAsSentByThePlugin(): void
    {
        $this->recordReminder($this->reviewAssignmentId, self::FIRST_REMINDER_DATE, self::CORE_AUTOMATIC_REMINDER_MESSAGE);

        $this->assertSame($this->renderCoreReviewHistory(), $this->renderReviewHistory());
    }

    public function testShouldKeepCoreHistoryWhenNoReviewAssignmentIsAuthorized(): void
    {
        $this->recordReminder($this->reviewAssignmentId, self::FIRST_REMINDER_DATE);
        $this->authorizeReviewAssignment(null);

        $this->assertSame($this->renderCoreReviewHistory(), $this->renderReviewHistory());
    }
}

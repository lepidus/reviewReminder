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
    private const REVIEW_ASSIGNMENT_ID = 11;
    private const OTHER_REVIEW_ASSIGNMENT_ID = 12;
    private const FIRST_REMINDER_DATE = '2026-10-05 08:30:00';
    private const SECOND_REMINDER_DATE = '2026-10-12 14:45:00';
    private const CORE_REVIEW_HISTORY_TEMPLATE = 'workflow/reviewHistory.tpl';

    private $pluginSettingsDao;
    private $lastEventLogIdBeforeTest;
    private $templateManager;
    private $router;
    private $fetchHooksBeforeTest;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $this->pluginSettingsDao->updateSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled', true, 'bool');
        $this->lastEventLogIdBeforeTest = DB::table('event_log')->max('log_id');

        $this->fetchHooksBeforeTest = Hook::getHooks('TemplateManager::fetch');
        $request = $this->createReviewHistoryRequest();
        $this->authorizeReviewAssignment(self::REVIEW_ASSIGNMENT_ID);
        $plugin = new ReviewReminderPlugin();
        $plugin->register('generic', self::PLUGIN_PATH, self::CONTEXT_ID);
        $this->templateManager = TemplateManager::getManager($request);
    }

    protected function tearDown(): void
    {
        $createdEventLogIds = DB::table('event_log')->where('log_id', '>', $this->lastEventLogIdBeforeTest)->pluck('log_id');
        DB::table('event_log_settings')->whereIn('log_id', $createdEventLogIds)->delete();
        DB::table('event_log')->whereIn('log_id', $createdEventLogIds)->delete();

        $this->pluginSettingsDao->deleteSetting(self::CONTEXT_ID, self::PLUGIN_NAME, 'enabled');
        $hooks = &Hook::getHooks();
        $hooks['TemplateManager::fetch'] = $this->fetchHooksBeforeTest;

        parent::tearDown();
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

    private function recordReminder(int $reviewAssignmentId, string $dateLogged): void
    {
        $reviewAssignment = DAORegistry::getDAO('ReviewAssignmentDAO')->getById($reviewAssignmentId);
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        Repo::eventLog()->add(Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => self::SUBMISSION_ID,
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO,
            'userId' => null,
            'message' => 'submission.event.reviewer.reviewerRemindedAuto',
            'isTranslated' => false,
            'dateLogged' => $dateLogged,
            'recipientId' => $reviewer->getId(),
            'recipientName' => $reviewer->getFullName(),
            'reviewAssignmentId' => $reviewAssignmentId,
        ]));
    }

    private function renderReviewHistory(): string
    {
        $reviewAssignment = DAORegistry::getDAO('ReviewAssignmentDAO')->getById(self::REVIEW_ASSIGNMENT_ID);
        $this->templateManager->assign('dates', [
            'common.assigned' => $reviewAssignment->getDateAssigned(),
            'common.notified' => $reviewAssignment->getDateNotified(),
            'common.reminder' => $reviewAssignment->getDateReminded(),
        ]);

        return $this->templateManager->fetch(self::CORE_REVIEW_HISTORY_TEMPLATE);
    }

    private function formatHistoryDate(string $date): string
    {
        $format = $this->templateManager->getTemplateVars('datetimeFormatShort');
        return (new Carbon($date))->locale(Locale::getLocale())->translatedFormat($format);
    }

    public function testShouldListEveryReminderSentForTheReviewAssignment(): void
    {
        $this->recordReminder(self::REVIEW_ASSIGNMENT_ID, self::FIRST_REMINDER_DATE);
        $this->recordReminder(self::REVIEW_ASSIGNMENT_ID, self::SECOND_REMINDER_DATE);

        $reviewHistory = $this->renderReviewHistory();

        $this->assertStringContainsString($this->formatHistoryDate(self::FIRST_REMINDER_DATE), $reviewHistory);
        $this->assertStringContainsString($this->formatHistoryDate(self::SECOND_REMINDER_DATE), $reviewHistory);
    }

    public function testShouldKeepReviewAssignmentDatesInChronologicalOrder(): void
    {
        $reviewAssignment = DAORegistry::getDAO('ReviewAssignmentDAO')->getById(self::REVIEW_ASSIGNMENT_ID);
        $this->recordReminder(self::REVIEW_ASSIGNMENT_ID, self::SECOND_REMINDER_DATE);
        $this->recordReminder(self::REVIEW_ASSIGNMENT_ID, self::FIRST_REMINDER_DATE);

        $reviewHistory = $this->renderReviewHistory();

        $assignedPosition = strpos($reviewHistory, $this->formatHistoryDate($reviewAssignment->getDateAssigned()));
        $firstReminderPosition = strpos($reviewHistory, $this->formatHistoryDate(self::FIRST_REMINDER_DATE));
        $secondReminderPosition = strpos($reviewHistory, $this->formatHistoryDate(self::SECOND_REMINDER_DATE));
        $this->assertNotFalse($assignedPosition);
        $this->assertLessThan($firstReminderPosition, $assignedPosition);
        $this->assertLessThan($secondReminderPosition, $firstReminderPosition);
    }

    public function testShouldNotListRemindersOfOtherReviewAssignments(): void
    {
        $this->recordReminder(self::OTHER_REVIEW_ASSIGNMENT_ID, self::FIRST_REMINDER_DATE);

        $reviewHistory = $this->renderReviewHistory();

        $this->assertStringNotContainsString($this->formatHistoryDate(self::FIRST_REMINDER_DATE), $reviewHistory);
    }

    public function testShouldKeepCoreHistoryWhenNoReviewAssignmentIsAuthorized(): void
    {
        $this->recordReminder(self::REVIEW_ASSIGNMENT_ID, self::FIRST_REMINDER_DATE);
        $this->authorizeReviewAssignment(null);

        $reviewHistory = $this->renderReviewHistory();

        $this->assertStringNotContainsString($this->formatHistoryDate(self::FIRST_REMINDER_DATE), $reviewHistory);
    }
}

<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\core\Application;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\template\TemplateManager;
use PKP\controllers\grid\users\reviewer\PKPReviewerGridHandler;
use PKP\core\PKPApplication;
use PKP\plugins\Hook;

class ReviewHistory
{
    private const CORE_TEMPLATE = 'workflow/reviewHistory.tpl';

    private bool $isFetchingCoreTemplate = false;

    public function __construct(private ReviewReminderPlugin $plugin)
    {
    }

    public function addReminders(string $hookName, array $args): bool
    {
        [$templateManager, $template, $cacheId, $compileId] = $args;
        $output = &$args[4];

        if ($template !== self::CORE_TEMPLATE || $this->isFetchingCoreTemplate) {
            return Hook::CONTINUE;
        }

        $handler = Application::get()->getRequest()->getRouter()?->getHandler();
        if (!$handler instanceof PKPReviewerGridHandler) {
            return Hook::CONTINUE;
        }

        $reviewAssignment = $handler->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        if (!$reviewAssignment) {
            return Hook::CONTINUE;
        }

        $reminderDates = (new ReminderActivityLog())->getReminderDates($reviewAssignment);
        if (empty($reminderDates)) {
            return Hook::CONTINUE;
        }

        $output = $this->fetchCoreTemplate($templateManager, $cacheId, $compileId)
            . $this->fetchReminders($templateManager, $reminderDates);

        return Hook::ABORT;
    }

    private function fetchCoreTemplate(TemplateManager $templateManager, ?string $cacheId, ?string $compileId): string
    {
        $this->isFetchingCoreTemplate = true;
        try {
            return $templateManager->fetch(self::CORE_TEMPLATE, $cacheId, $compileId);
        } finally {
            $this->isFetchingCoreTemplate = false;
        }
    }

    private function fetchReminders(TemplateManager $templateManager, array $reminderDates): string
    {
        $templateManager->assign('reviewReminderDates', $reminderDates);
        return $templateManager->fetch($this->plugin->getTemplateResource('reviewHistory.tpl'));
    }
}

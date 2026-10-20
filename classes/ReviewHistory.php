<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\core\Application;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use PKP\controllers\grid\users\reviewer\PKPReviewerGridHandler;
use PKP\core\PKPApplication;
use PKP\plugins\Hook;

class ReviewHistory
{
    private const CORE_TEMPLATE = 'workflow/reviewHistory.tpl';
    private const REMINDER_LOCALE_KEYS = [
        ReminderActivityLog::PENDING_REVIEWS_REMINDER_MESSAGE => 'plugins.generic.reviewReminder.history.pendingReviewsReminder',
        ReminderActivityLog::REVIEW_REMINDER_MESSAGE => 'plugins.generic.reviewReminder.history.reviewReminder',
    ];

    private $plugin;
    private $isFetchingCoreTemplate = false;

    public function __construct(ReviewReminderPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function addReminders($hookName, $args)
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

        $reminders = (new ReminderActivityLog())->getReminders($reviewAssignment);
        if (empty($reminders)) {
            return Hook::CONTINUE;
        }

        $output = $this->fetchCoreTemplate($templateManager, $cacheId, $compileId)
            . $this->fetchReminders($templateManager, $reminders);

        return Hook::ABORT;
    }

    private function fetchCoreTemplate($templateManager, $cacheId, $compileId)
    {
        $this->isFetchingCoreTemplate = true;
        try {
            return $templateManager->fetch(self::CORE_TEMPLATE, $cacheId, $compileId);
        } finally {
            $this->isFetchingCoreTemplate = false;
        }
    }

    private function fetchReminders($templateManager, $reminders)
    {
        $reviewReminderEntries = array_map(
            fn ($reminder) => ['date' => $reminder->date, 'localeKey' => self::REMINDER_LOCALE_KEYS[$reminder->message]],
            $reminders
        );
        $templateManager->assign('reviewReminderEntries', $reviewReminderEntries);
        return $templateManager->fetch($this->plugin->getTemplateResource('reviewHistory.tpl'));
    }
}

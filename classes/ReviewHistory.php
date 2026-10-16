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
    private const REMINDER_LOCALE_KEY = 'common.reminder';

    private $plugin;

    public function __construct(ReviewReminderPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function addReminders($hookName, $args)
    {
        $templateManager = $args[0];
        $template = $args[1];
        $output = &$args[4];

        if ($template !== self::CORE_TEMPLATE) {
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

        $templateManager->assign('reviewHistoryEntries', $this->getHistoryEntries($templateManager->getTemplateVars('dates') ?? [], $reviewAssignment));
        $output = $templateManager->fetch($this->plugin->getTemplateResource('reviewHistory.tpl'));

        return Hook::ABORT;
    }

    private function getHistoryEntries($reviewAssignmentDates, $reviewAssignment)
    {
        $entries = [];
        foreach ($reviewAssignmentDates as $localeKey => $date) {
            if ($date) {
                $entries[] = ['date' => $date, 'localeKey' => $localeKey];
            }
        }

        foreach ((new ReminderActivityLog())->getReminderDates($reviewAssignment) as $date) {
            $entries[] = ['date' => $date, 'localeKey' => self::REMINDER_LOCALE_KEY];
        }

        usort($entries, fn ($entry, $otherEntry) => strtotime($entry['date']) <=> strtotime($otherEntry['date']));

        return $entries;
    }
}

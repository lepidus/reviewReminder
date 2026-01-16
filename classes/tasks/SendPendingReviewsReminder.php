<?php

namespace APP\plugins\generic\reviewReminder\classes\tasks;

use APP\core\Application;
use PKP\scheduledTask\ScheduledTask;
use APP\facades\Repo;
use PKP\security\Role;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderDAO;

class SendModerationReminders extends ScheduledTask
{
    private $plugin;

    public function executeActions()
    {
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll(true);
        $reviewReminderDao = new ReviewReminderDAO();

        while ($context = $contexts->next()) {
            $reviewers = $this->getReviewersFromContext($context->getId());

            foreach ($reviewers as $reviewer) {
                $reviewerIncompleteReviews = $reviewReminderDao->getIncompleteReviewsByReviewer($reviewer->getId());

                if (count($reviewerIncompleteReviews) > 0) {
                    // reminder builder
                }
            }
        }
    }

    public function getReviewersFromContext($contextId)
    {
        return Repo::user()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
            ->getMany()
            ->toArray();
    }
}

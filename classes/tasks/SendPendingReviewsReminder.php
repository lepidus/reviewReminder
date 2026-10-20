<?php

namespace APP\plugins\generic\reviewReminder\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use APP\core\Application;
use APP\facades\Repo;
use PKP\security\Role;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderDAO;
use APP\plugins\generic\reviewReminder\classes\PendingReviewsEmailBuilder;
use APP\plugins\generic\reviewReminder\classes\ReminderActivityLog;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;

class SendPendingReviewsReminder extends ScheduledTask
{
    public function executeActions()
    {
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll(true);
        $reviewReminderDao = new ReviewReminderDAO();
        $plugin = new ReviewReminderPlugin();
        $reminderActivityLog = new ReminderActivityLog();

        while ($context = $contexts->next()) {
            if (!$plugin->getEnabled($context->getId())) {
                continue;
            }

            $reviewers = $this->getReviewersFromContext($context->getId());

            foreach ($reviewers as $reviewer) {
                $reviewerIncompleteReviews = $reviewReminderDao->getIncompleteReviewsByReviewer($reviewer->getId(), $context->getId());

                if (empty($reviewerIncompleteReviews)) {
                    continue;
                }

                $reviewerSubmissions = [];
                foreach ($reviewerIncompleteReviews as $review) {
                    $reviewSubmission = Repo::submission()->get($review->getData('submissionId'));
                    $reviewerSubmissions[] = [
                        'submission' => $reviewSubmission,
                        'reviewDueDate' => $review->getData('dateDue')
                    ];
                }

                $pendingReviewsEmailBuilder = new PendingReviewsEmailBuilder(
                    $context,
                    $reviewer,
                    $reviewerSubmissions,
                    $context->getData('primaryLocale')
                );

                $email = $pendingReviewsEmailBuilder->buildEmail();
                Mail::send($email);

                foreach ($reviewerIncompleteReviews as $review) {
                    $reminderActivityLog->recordReminder($review, $reviewer, ReminderActivityLog::PENDING_REVIEWS_REMINDER_MESSAGE);
                }
            }
        }

        return true;
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

<?php

namespace APP\plugins\generic\reviewReminder\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use APP\core\Application;
use APP\facades\Repo;
use PKP\security\Role;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderDAO;
use APP\plugins\generic\reviewReminder\classes\PendingReviewsEmailBuilder;

class SendModerationReminders extends ScheduledTask
{
    public function executeActions()
    {
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll(true);
        $reviewReminderDao = new ReviewReminderDAO();

        while ($context = $contexts->next()) {
            $reviewers = $this->getReviewersFromContext($context->getId());

            foreach ($reviewers as $reviewer) {
                $reviewerIncompleteReviews = $reviewReminderDao->getIncompleteReviewsByReviewer($reviewer->getId());

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

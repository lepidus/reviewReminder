<?php

namespace APP\plugins\generic\reviewReminder\classes\tasks;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\PendingReviewsEmailBuilder;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderDAO;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use Illuminate\Support\Facades\Mail;
use PKP\scheduledTask\ScheduledTask;
use PKP\security\Role;
use PKP\submission\PKPSubmission;

class SendPendingReviewsReminder extends ScheduledTask
{
    public function __construct(private ReviewReminderPlugin $plugin, array $args = [])
    {
        parent::__construct($args);
    }

    protected function executeActions(): bool
    {
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll(true);
        $reviewReminderDao = new ReviewReminderDAO();

        while ($context = $contexts->next()) {
            if (!$this->plugin->getEnabled($context->getId())) {
                continue;
            }

            $submissions = [];
            $pendingReviewsByReviewer = [];
            $incompleteReviews = $reviewReminderDao->getIncompleteReviewsByContext($context->getId());

            foreach ($incompleteReviews as $reviewAssignment) {
                $submissionId = $reviewAssignment->getSubmissionId();
                if (!array_key_exists($submissionId, $submissions)) {
                    $submissions[$submissionId] = Repo::submission()->get($submissionId);
                }

                $submission = $submissions[$submissionId];
                if (!$submission || $submission->getData('status') !== PKPSubmission::STATUS_QUEUED) {
                    continue;
                }

                $pendingReviewsByReviewer[$reviewAssignment->getReviewerId()][] = [
                    'submission' => $submission,
                    'reviewDueDate' => $reviewAssignment->getDateDue(),
                ];
            }

            $activeReviewerIds = $this->getActiveReviewersIds($context->getId());

            foreach ($pendingReviewsByReviewer as $reviewerId => $reviewerSubmissions) {
                if (!in_array($reviewerId, $activeReviewerIds)) {
                    continue;
                }

                $reviewer = Repo::user()->get($reviewerId);
                if (!$reviewer) {
                    continue;
                }

                $pendingReviewsEmailBuilder = new PendingReviewsEmailBuilder(
                    $context,
                    $reviewer,
                    $reviewerSubmissions,
                    $context->getPrimaryLocale()
                );

                $email = $pendingReviewsEmailBuilder->buildEmail();
                Mail::send($email);
            }
        }

        return true;
    }

    public function getActiveReviewersIds(int $contextId): array
    {
        $userCollector = Repo::user()->getCollector();
        $activeReviewerIds = $userCollector
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
            ->filterByStatus($userCollector::STATUS_ACTIVE)
            ->getIds()
            ->toArray();
        return $activeReviewerIds;
    }
}

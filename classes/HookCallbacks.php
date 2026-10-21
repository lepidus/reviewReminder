<?php

namespace APP\plugins\generic\reviewReminder\classes;

use PKP\db\DAORegistry;
use APP\core\Application;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderService;

class HookCallbacks
{
    public function sendReviewReminder($hookName, $args)
    {
        $reviewerDeclined = $args[3];
        if ($reviewerDeclined) {
            return;
        }

        $submission = $args[1];
        $mailable = $args[2];
        $reviewer = $mailable->getSenderUser();
        $reviewAssignment = $this->getReviewAssignment($submission->getId(), $reviewer->getId());

        if (is_null($reviewAssignment)) {
            return;
        }

        $reviewDueDate = $reviewAssignment->getDateDue();
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $reviewReminderService = new ReviewReminderService(
            $context,
            $reviewAssignment,
            $reviewer,
            $reviewDueDate
        );

        $reviewReminderService->sendReviewReminder();
    }

    private function getReviewAssignment($submissionId, $reviewerId)
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        return $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submissionId, $reviewerId);
    }
}

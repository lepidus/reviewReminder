<?php

namespace APP\plugins\generic\reviewReminder\classes;

use PKP\db\DAORegistry;
use APP\core\Application;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderService;
use PKP\plugins\Hook;

class HookCallbacks
{
    private $reviewBeingAccepted = null;

    public function rememberReviewBeingAccepted($hookName, $args)
    {
        $this->reviewBeingAccepted = $args[0]->getReviewAssignment();
        return Hook::CONTINUE;
    }

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
        $reviewBeingAccepted = $this->reviewBeingAccepted;
        $this->reviewBeingAccepted = null;
        if (
            $reviewBeingAccepted
            && $reviewBeingAccepted->getSubmissionId() == $submissionId
            && $reviewBeingAccepted->getReviewerId() == $reviewerId
        ) {
            return $reviewBeingAccepted;
        }

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        return $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submissionId, $reviewerId);
    }
}

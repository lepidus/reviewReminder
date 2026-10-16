<?php

namespace APP\plugins\generic\reviewReminder\classes;

use PKP\db\DAORegistry;
use APP\core\Application;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderService;
use PKP\security\AccessKeyManager;

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

        $oneClickReviewerUrl = $this->getOneClickReviewerUrl(
            $context,
            $reviewer->getId(),
            $reviewAssignment->getId(),
            $reviewAssignment->getSubmissionId(),
            $request
        );

        $reviewReminderService = new ReviewReminderService(
            $context,
            $reviewAssignment,
            $reviewer,
            $reviewDueDate,
            $oneClickReviewerUrl
        );

        $reviewReminderService->sendReviewReminder();
    }

    private function getReviewAssignment($submissionId, $reviewerId)
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        return $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submissionId, $reviewerId);
    }

    private function getOneClickReviewerUrl($context, $reviewerId, $reviewAssignmentId, $submissionId, $request)
    {
        $reviewerAccessKeysEnabled = $context->getData('reviewerAccessKeysEnabled');
        if (!$reviewerAccessKeysEnabled) {
            return null;
        }

        $accessKeyManager = new AccessKeyManager();
        $keyLifetime = ($context->getData('numWeeksPerReview') + 4) * 7;
        $accessKey = $accessKeyManager->createKey($context->getId(), $reviewerId, $reviewAssignmentId, $keyLifetime);

        $reviewUrlArgs = [
            'submissionId' => $submissionId,
            'reviewId' => $reviewAssignmentId,
            'key' => $accessKey
        ];

        return Application::get()->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'reviewer', 'submission', null, $reviewUrlArgs);
    }
}

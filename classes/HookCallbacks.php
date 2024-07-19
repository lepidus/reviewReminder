<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
use APP\core\Application;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderService;
use PKP\security\AccessKeyManager;

class HookCallbacks
{
    public function getReviewMetadata($hookName, $args)
    {
        $reviewAssignment = $args[0];
        $reviewer = $args[1];
        $reviewDueDate = $args[2];
        $submission = Repo::submission()->get((int) $reviewAssignment->getSubmissionId());
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $reviewerAccessKeysEnabled = $context->getData('reviewerAccessKeysEnabled');
        $reviewId = $reviewAssignment->getId();
        $submissionReviewUrl = $this->getSubmissionReviewUrl(
            $context,
            $reviewerAccessKeysEnabled,
            $reviewer->getId(),
            $reviewAssignment->getId(),
            $reviewAssignment->getSubmissionId(),
            $request
        );

        $reviewReminderService = new ReviewReminderService(
            $reviewer->getEmail(),
            $reviewDueDate,
            $submission->getLocalizedTitle(),
            $context->getData('contactEmail'),
            $context->getData('contactName'),
            $context->getLocalizedName(),
            $submissionReviewUrl
        );

        $reviewReminderService->sendReviewReminder();
    }

    private function getSubmissionReviewUrl($context, $reviewerAccessKeysEnabled, $reviewerId, $reviewAssignmentId, $submissionId, $request)
    {
        if ($reviewerAccessKeysEnabled) {
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
}

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

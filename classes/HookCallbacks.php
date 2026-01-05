<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
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
        $reviewAssignment = null;
        foreach ($mailable->getVariables() as $variable) {
            if (get_class($variable) == 'PKP\mail\variables\ReviewAssignmentEmailVariable') {
                $values = $variable->values('');
                $reviewAssignment = $this->getReviewAssignment($submission->getId(), $values['reviewerName']);
                break;
            }
        }

        if (is_null($reviewAssignment)) {
            return;
        }

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
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

    private function getReviewAssignment($submissionId, $reviewerFullName)
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submissionId);

        foreach ($reviewAssignments as $reviewAssignment) {
            if ($reviewAssignment->getReviewerFullName() == $reviewerFullName) {
                return $reviewAssignment;
            }
        }
        return null;
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

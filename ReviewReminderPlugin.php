<?php

/**
 * @file plugins/generic/reviewReminder/ReviewReminderPlugin.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ReviewReminderPlugin
 * @ingroup plugins_generic_reviewReminder
 *
 * @brief Plugin for sending review reminder emails to designated reviewers
 */

namespace APP\plugins\generic\reviewReminder;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\facades\Repo;
use APP\core\Application;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderService;
use PKP\security\AccessKeyManager;

class ReviewReminderPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled()) {
            Hook::add('EditorAction::setDueDates', [$this, 'getReviewMetadata']);
        }
        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.reviewReminder.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.reviewReminder.description');
    }

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

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
use APP\plugins\generic\reviewReminder\lib\ICS;
use APP\plugins\generic\reviewReminder\classes\ReminderFile;
use APP\core\Application;
use Illuminate\Support\Facades\Mail;
use PKP\mail\Mailable;

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

        $ics = new ICS(array(
            'description' => __('plugins.generic.reviewReminder.ics.description', ['submissionTitle' => $submission->getLocalizedTitle()]),
            'dtstart' => 'now',
            'dtend' => $reviewDueDate,
            'summary' => __('plugins.generic.reviewReminder.displayName')
        ));

        $filePath = ReminderFile::create($ics);
        $mailable = new Mailable();
        $mailable->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->to($reviewer->getEmail())
            ->subject(__('plugins.generic.reviewReminder.displayName'))
            ->body(__('plugins.generic.reviewReminder.email.body'))
            ->attach($filePath, ['as' => 'invite.ics']);

        Mail::send($mailable);
    }
}

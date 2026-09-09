<?php

/**
 * @file plugins/generic/reviewReminder/ReviewReminderPlugin.inc.php
 *
 * Copyright (c) 2024 - 2025 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ReviewReminderPlugin
 *
 * @ingroup plugins_generic_reviewReminder
 *
 * @brief Plugin for sending review reminder emails to designated reviewers
 */

namespace APP\plugins\generic\reviewReminder;

use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
use APP\plugins\generic\reviewReminder\classes\tasks\SendPendingReviewsReminder;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\scheduledTask\PKPScheduler;

class ReviewReminderPlugin extends GenericPlugin implements HasTaskScheduler
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            $hookCallbacks = new HookCallbacks();
            Hook::add('EditorAction::setDueDates', [$hookCallbacks, 'rememberAssignedReview']);
            Hook::add('Mailable::build', [$hookCallbacks, 'attachCalendarInvite']);
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

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'emailTemplates.xml';
    }

    public function registerSchedules(PKPScheduler $scheduler): void
    {
        $scheduler
            ->addSchedule(new SendPendingReviewsReminder())
            ->weeklyOn(1)
            ->name(SendPendingReviewsReminder::class)
            ->withoutOverlapping();
    }
}

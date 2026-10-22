<?php

/**
 * @file plugins/generic/reviewReminder/ReviewReminderPlugin.inc.php
 *
 * Copyright (c) 2024 - 2025 Lepidus Tecnologia
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
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;
use APP\plugins\generic\reviewReminder\classes\PendingReviewsReminderSetting;
use APP\plugins\generic\reviewReminder\classes\ReviewHistory;

class ReviewReminderPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success) {
            return $success;
        }

        Hook::add('AcronPlugin::parseCronTab', [$this, 'addTasksToCrontab']);

        if ($this->getEnabled()) {
            $hookCallbacks = new HookCallbacks();
            Hook::add('pkpreviewerreviewstep1form::readuservars', [$hookCallbacks, 'rememberReviewBeingAccepted']);
            Hook::add('ReviewerAction::confirmReview', [$hookCallbacks, 'sendReviewReminder']);
            Hook::add('TemplateManager::fetch', [new ReviewHistory($this), 'addReminders']);
            Hook::add('Mailer::Mailables', [$this, 'addMailables']);
            $pendingReviewsReminderSetting = new PendingReviewsReminderSetting();
            Hook::add('Schema::get::before::context', [$pendingReviewsReminderSetting, 'reloadContextSchema']);
            Hook::add('Schema::get::context', [$pendingReviewsReminderSetting, 'addToContextSchema']);
            Hook::add('Form::config::before', [$pendingReviewsReminderSetting, 'addToReviewSetupForm']);
        }
        return $success;
    }

    public function addMailables($hookName, $args)
    {
        $mailables = $args[0];
        $mailables->push(PendingReviewsReminder::class);

        return Hook::CONTINUE;
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

    public function addTasksToCrontab($hookName, $params)
    {
        if (!$this->isEnabledInAnyContext()) {
            return Hook::CONTINUE;
        }

        $taskFilesPath = &$params[0];
        $taskFilesPath[] = $this->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasks.xml';
        return Hook::CONTINUE;
    }

    private function isEnabledInAnyContext(): bool
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', $this->getName())
            ->where('setting_name', 'enabled')
            ->where('setting_value', '1')
            ->exists();
    }
}

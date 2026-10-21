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

use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
use APP\plugins\generic\reviewReminder\classes\ReviewHistory;
use APP\plugins\generic\reviewReminder\classes\ReviewReminderSettingsForm;

class ReviewReminderPlugin extends GenericPlugin
{
    public const PENDING_REVIEWS_REMINDER_ENABLED = 'pendingReviewsReminderEnabled';

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

    public function isPendingReviewsReminderEnabled($contextId)
    {
        return (bool) ($this->getSetting($contextId, self::PENDING_REVIEWS_REMINDER_ENABLED) ?? true);
    }

    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }

    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $form = new ReviewReminderSettingsForm($this, $request->getContext()->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        return new JSONMessage(true);
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

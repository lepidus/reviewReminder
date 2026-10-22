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

use APP\core\Application;
use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;
use APP\plugins\generic\reviewReminder\classes\PendingReviewsReminderSetting;
use APP\plugins\generic\reviewReminder\classes\ReviewHistory;
use APP\plugins\generic\reviewReminder\classes\tasks\SendPendingReviewsReminder;
use Carbon\Carbon;
use Carbon\WeekDay;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\scheduledTask\PKPScheduler;

class ReviewReminderPlugin extends GenericPlugin implements HasTaskScheduler
{
    public const PENDING_REVIEWS_REMINDER_LAST_SENT_AT = 'pendingReviewsReminderLastSentAt';

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            $hookCallbacks = new HookCallbacks();
            Hook::add('EditorAction::setDueDates', [$hookCallbacks, 'rememberAssignedReview']);
            Hook::add('Mailable::build', [$hookCallbacks, 'attachCalendarInvite']);
            Hook::add('TemplateManager::fetch', [new ReviewHistory($this), 'addReminders']);
            Hook::add('Mailer::Mailables', [$this, 'addMailables']);
            $pendingReviewsReminderSetting = new PendingReviewsReminderSetting();
            Hook::add('Schema::get::before::context', [$pendingReviewsReminderSetting, 'reloadContextSchema']);
            Hook::add('Schema::get::context', [$pendingReviewsReminderSetting, 'addToContextSchema']);
            Hook::add('Form::config::before', [$pendingReviewsReminderSetting, 'addToReviewSetupForm']);
        }
        return $success;
    }

    public function addMailables(string $hookName, array $args): bool
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

    public function shouldSendPendingReviewsReminder(int $contextId): bool
    {
        return $this->getEnabled($contextId)
            && PendingReviewsReminderSetting::isEnabled($contextId)
            && $this->isPendingReviewsReminderDue($contextId);
    }

    public function recordPendingReviewsReminder(int $contextId): void
    {
        $this->updateSetting($contextId, self::PENDING_REVIEWS_REMINDER_LAST_SENT_AT, Carbon::now()->toDateTimeString(), 'string');
    }

    public function hasPendingReviewsReminderToSend(): bool
    {
        $contexts = Application::getContextDAO()->getAll(true);
        while ($context = $contexts->next()) {
            if ($this->shouldSendPendingReviewsReminder($context->getId())) {
                return true;
            }
        }

        return false;
    }

    private function isPendingReviewsReminderDue(int $contextId): bool
    {
        $lastSentAt = $this->getSetting($contextId, self::PENDING_REVIEWS_REMINDER_LAST_SENT_AT);
        if (!$lastSentAt) {
            return Carbon::now()->isMonday();
        }

        return Carbon::parse($lastSentAt)->lt(Carbon::now()->startOfWeek(WeekDay::Monday));
    }

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'emailTemplates.xml';
    }

    public function registerSchedules(PKPScheduler $scheduler): void
    {
        $scheduler
            ->addSchedule(new SendPendingReviewsReminder($this))
            ->everyMinute()
            ->when(fn () => $this->hasPendingReviewsReminderToSend())
            ->name(SendPendingReviewsReminder::class)
            ->withoutOverlapping();
    }
}

<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class ReviewReminderSettingsForm extends Form
{
    private $plugin;
    private $contextId;

    public function __construct(ReviewReminderPlugin $plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $this->setData(
            ReviewReminderPlugin::PENDING_REVIEWS_REMINDER_ENABLED,
            $this->plugin->isPendingReviewsReminderEnabled($this->contextId)
        );
    }

    public function readInputData()
    {
        $this->readUserVars([ReviewReminderPlugin::PENDING_REVIEWS_REMINDER_ENABLED]);
    }

    public function fetch($request, $template = null, $display = false)
    {
        TemplateManager::getManager($request)->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $this->plugin->updateSetting(
            $this->contextId,
            ReviewReminderPlugin::PENDING_REVIEWS_REMINDER_ENABLED,
            (bool) $this->getData(ReviewReminderPlugin::PENDING_REVIEWS_REMINDER_ENABLED),
            'bool'
        );

        parent::execute(...$functionArgs);
    }
}

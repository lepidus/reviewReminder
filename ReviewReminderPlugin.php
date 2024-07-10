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
use PKP\db\DAORegistry;
use APP\plugins\generic\reviewReminder\lib\ICS;

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
        $reviewerAssignment = $args[0];
        $reviewer = $args[1];
        $reviewDueDate = $args[2];

        $ics = new ICS(array(
            'description' => "Início do período de revisão hoje, 6 de junho de 2024. Término do período de revisão é dia 30 de junho de 2024.",
            'dtstart' => 'now',
            'dtend' => $reviewDueDate,
            'summary' => "Prazo de avaliação"
        ));
    }

}

<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\components\forms\context\PKPReviewSetupForm;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\plugins\Hook;

class PendingReviewsReminderSetting
{
    public const NAME = 'pendingReviewsReminderEnabled';
    private const REVIEW_REMINDER_GROUP = 'reviewReminderGroup';
    private const LAST_CORE_REMINDER_FIELD = 'numDaysAfterReviewSubmitReminderDue';

    private bool $isAddedToContextSchema = false;

    public static function isEnabled(int $contextId): bool
    {
        $contextDao = Application::getContextDAO();
        $value = DB::table($contextDao->settingsTableName)
            ->where($contextDao->primaryKeyColumn, $contextId)
            ->where('setting_name', self::NAME)
            ->value('setting_value');

        return $value === null || (bool) $value;
    }

    public function reloadContextSchema(string $hookName, bool &$forceReload): bool
    {
        if (!$this->isAddedToContextSchema) {
            $forceReload = true;
        }

        return Hook::CONTINUE;
    }

    public function addToContextSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        $schema->properties->{self::NAME} = (object) [
            'type' => 'boolean',
            'validation' => ['nullable'],
        ];
        $this->isAddedToContextSchema = true;

        return Hook::CONTINUE;
    }

    public function addToReviewSetupForm(string $hookName, FormComponent $form): bool
    {
        if (!$form instanceof PKPReviewSetupForm) {
            return Hook::CONTINUE;
        }

        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            return Hook::CONTINUE;
        }

        $form->addField(new FieldOptions(self::NAME, [
            'label' => __('plugins.generic.reviewReminder.displayName'),
            'description' => __('plugins.generic.reviewReminder.settings.pendingReviewsReminder.description'),
            'type' => 'checkbox',
            'value' => self::isEnabled($context->getId()),
            'options' => [
                ['value' => true, 'label' => __('plugins.generic.reviewReminder.settings.pendingReviewsReminder')],
            ],
            'groupId' => self::REVIEW_REMINDER_GROUP,
        ]), [FIELD_POSITION_AFTER, self::LAST_CORE_REMINDER_FIELD]);

        return Hook::CONTINUE;
    }
}

<?php

namespace APP\plugins\generic\reviewReminder\classes\mail\mailables;

use APP\journal\Journal;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\security\Role;

class PendingReviewsReminder extends Mailable
{
    use Configurable;

    public const REVIEWER_NAME = 'reviewerName';
    public const SUBMISSIONS_LIST = 'submissionsList';

    protected static ?string $name = 'emails.pendingReviewsReminder.name';
    protected static ?string $description = 'emails.pendingReviewsReminder.description';
    protected static ?string $emailTemplateKey = 'PENDING_REVIEWS_REMINDER';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    public function __construct(Journal $context, array $variables = [])
    {
        parent::__construct([$context]);
        $this->addData($variables);
    }

    public static function getDataDescriptions(): array
    {
        return array_merge(
            parent::getDataDescriptions(),
            [
                self::REVIEWER_NAME => __('plugins.generic.reviewReminder.emailVariable.reviewerName'),
                self::SUBMISSIONS_LIST => __('plugins.generic.reviewReminder.emailVariable.submissionsList'),
            ]
        );
    }
}

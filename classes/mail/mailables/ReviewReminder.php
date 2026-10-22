<?php

namespace APP\plugins\generic\reviewReminder\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\journal\Journal;
use APP\submission\Submission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\mail\traits\Configurable;
use PKP\security\Role;

class ReviewReminder extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.reviewReminder.name';
    protected static ?string $description = 'emails.reviewReminder.description';
    protected static ?string $emailTemplateKey = 'REVIEW_REMINDER';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    public function __construct(Journal $context, Submission $submission, ReviewAssignment $reviewAssignment, array $variables = [])
    {
        parent::__construct([$context, $submission, $reviewAssignment]);
        $this->addData($variables);
    }
}

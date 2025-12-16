<?php

namespace APP\plugins\generic\reviewReminder\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\journal\Journal;
use APP\submission\Submission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\mail\traits\Configurable;

class ReviewReminder extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.reviewReminder.name';
    protected static ?string $description = 'emails.reviewReminder.description';
    protected static ?string $emailTemplateKey = 'REVIEW_REMINDER';

    public function __construct(Journal $context, Submission $submission, ReviewAssignment $reviewAssignment, array $variables = [])
    {
        parent::__construct([$context, $submission, $reviewAssignment]);
        $this->addData($variables);
    }
}

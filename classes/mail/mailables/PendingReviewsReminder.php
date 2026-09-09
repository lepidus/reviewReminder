<?php

namespace APP\plugins\generic\reviewReminder\classes\mail\mailables;

use APP\journal\Journal;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;

class PendingReviewsReminder extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.pendingReviewsReminder.name';
    protected static ?string $description = 'emails.pendingReviewsReminder.description';
    protected static ?string $emailTemplateKey = 'PENDING_REVIEWS_REMINDER';

    public function __construct(Journal $context, array $variables = [])
    {
        parent::__construct([$context]);
        $this->addData($variables);
    }
}

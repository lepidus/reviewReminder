<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\user\User;

class ReminderActivityLog
{
    private const REMINDER_MESSAGE = 'submission.event.reviewer.reviewerRemindedAuto';

    public function recordReminder(ReviewAssignment $reviewAssignment, User $reviewer): void
    {
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $reviewAssignment->getSubmissionId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO,
            'userId' => null,
            'message' => self::REMINDER_MESSAGE,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'recipientId' => $reviewer->getId(),
            'recipientName' => $reviewer->getFullName(),
            'reviewAssignmentId' => $reviewAssignment->getId(),
        ]);
        Repo::eventLog()->add($eventLog);
    }
}

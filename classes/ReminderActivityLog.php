<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\user\User;

class ReminderActivityLog
{
    private const PENDING_REVIEWS_REMINDER_MESSAGE = 'plugins.generic.reviewReminder.event.pendingReviewsReminderSent';

    public function recordReminder(ReviewAssignment $reviewAssignment, User $reviewer): void
    {
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $reviewAssignment->getSubmissionId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO,
            'userId' => null,
            'message' => self::PENDING_REVIEWS_REMINDER_MESSAGE,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'recipientId' => $reviewer->getId(),
            'recipientName' => $reviewer->getFullName(),
            'reviewAssignmentId' => $reviewAssignment->getId(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    public function getReminderDates(ReviewAssignment $reviewAssignment): array
    {
        return DB::table('event_log as e')
            ->join('event_log_settings as s', 's.log_id', '=', 'e.log_id')
            ->where('e.assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
            ->where('e.assoc_id', $reviewAssignment->getSubmissionId())
            ->where('e.event_type', PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO)
            ->where('e.message', self::PENDING_REVIEWS_REMINDER_MESSAGE)
            ->where('s.setting_name', 'reviewAssignmentId')
            ->where('s.setting_value', (string) $reviewAssignment->getId())
            ->orderBy('e.date_logged')
            ->pluck('e.date_logged')
            ->all();
    }
}

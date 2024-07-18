<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\plugins\generic\reviewReminder\lib\ICS;
use APP\plugins\generic\reviewReminder\classes\ReminderFile;
use Illuminate\Support\Facades\Mail;
use PKP\mail\Mailable;

class ReviewReminderService
{
    private $reviewerEmail;
    private $reviewDueDate;
    private $submissionTitle;
    private $contactEmail;
    private $contactName;
    private $journalName;
    private $submissionReviewUrl;

    public function __construct(string $reviewerEmail, string $reviewDueDate, string $submissionTitle, string $contactEmail, string $contactName, string $journalName, string $submissionReviewUrl = null)
    {
        $this->reviewerEmail = $reviewerEmail;
        $this->reviewDueDate = $reviewDueDate;
        $this->submissionTitle = $submissionTitle;
        $this->contactEmail = $contactEmail;
        $this->contactName = $contactName;
        $this->journalName = $journalName;
        $this->submissionReviewUrl = $submissionReviewUrl;
    }

    public function sendReviewReminder()
    {
        $filePath = $this->createICalendarFile();
        $mailable = new Mailable();
        $mailable->from($this->contactEmail, $this->contactName)
            ->to($this->reviewerEmail)
            ->subject(__('plugins.generic.reviewReminder.displayName'))
            ->body(__('plugins.generic.reviewReminder.email.body'))
            ->attach($filePath, ['as' => 'invite.ics']);

        Mail::send($mailable);
    }

    private function createICalendarFile()
    {
        $ics = new ICS(array(
            'description' => __(
                'plugins.generic.reviewReminder.ics.description',
                [
                    'submissionTitle' => $this->submissionTitle,
                    'submissionReviewUrl' => $this->submissionReviewUrl
                ]
            ),
            'dtstart' => 'now',
            'dtend' => $this->reviewDueDate,
            'summary' => __(
                'plugins.generic.reviewReminder.ics.summary',
                ['journalName' => $this->journalName]
            ),
            'organizer' => $this->journalName . ':mailto:' . $this->contactEmail,
            'url' => $this->submissionReviewUrl
        ));

        return ReminderFile::create($ics);
    }
}

<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
use APP\core\Application;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\ReviewReminder;
use APP\plugins\generic\reviewReminder\lib\ICS;
use APP\plugins\generic\reviewReminder\classes\ReminderFile;
use APP\plugins\generic\reviewReminder\classes\ReminderActivityLog;

class ReviewReminderService
{
    private $context;
    private $submission;
    private $reviewAssignment;
    private $reviewer;
    private $reviewDueDate;

    private const EMAIL_TEMPLATE_KEY = 'REVIEW_REMINDER';

    public function __construct($context, $reviewAssignment, $reviewer, $reviewDueDate)
    {
        $this->context = $context;
        $this->submission = Repo::submission()->get((int) $reviewAssignment->getSubmissionId());
        $this->reviewAssignment = $reviewAssignment;
        $this->reviewer = $reviewer;
        $this->reviewDueDate = $reviewDueDate;
    }

    public function sendReviewReminder()
    {
        $calendarContents = $this->createICalendarContents();
        $this->reviewAssignment->setDateDue($this->reviewDueDate);
        $this->reviewAssignment->setReviewerFullName($this->reviewer->getFullName());

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $this->context->getId(),
            self::EMAIL_TEMPLATE_KEY
        );
        $email = new ReviewReminder($this->context, $this->submission, $this->reviewAssignment);
        $email->from($this->context->getData('contactEmail'), $this->context->getData('contactName'))
            ->to([['name' => $this->reviewer->getFullName(), 'email' => $this->reviewer->getEmail()]])
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'))
            ->attachData($calendarContents, 'invite.ics', ['mime' => 'text/calendar']);

        Mail::send($email);

        (new ReminderActivityLog())->recordReminder($this->reviewAssignment, $this->reviewer, ReminderActivityLog::REVIEW_REMINDER_MESSAGE);
    }

    private function createICalendarContents()
    {
        $submissionTitle = $this->submission->getLocalizedTitle();
        $reviewDueDate = new \DateTimeImmutable($this->reviewDueDate);

        $ics = new ICS(array(
            'description' => __(
                'plugins.generic.reviewReminder.ics.description',
                [
                    'submissionTitle' => $submissionTitle,
                    'submissionReviewUrl' => $this->getReviewUrl()
                ]
            ),
            'dtstart' => $reviewDueDate->format('Y-m-d'),
            'dtend' => $reviewDueDate->modify('+1 day')->format('Y-m-d'),
            'summary' => __(
                'plugins.generic.reviewReminder.ics.summary',
                [
                    'submissionTitle' => $submissionTitle,
                    'journalName' => $this->context->getLocalizedName()
                ]
            ),
            'organizer' => [
                'name' => $this->context->getLocalizedName(),
                'email' => $this->context->getData('contactEmail'),
            ],
        ));

        return ReminderFile::contents($ics);
    }

    private function getReviewUrl()
    {
        $application = Application::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
        return $dispatcher->url(
            $request,
            Application::ROUTE_PAGE,
            $this->context->getData('urlPath'),
            'reviewer',
            'submission',
            null,
            ['submissionId' => $this->reviewAssignment->getSubmissionId()]
        );
    }
}

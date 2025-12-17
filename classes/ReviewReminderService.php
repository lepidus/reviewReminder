<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
use APP\core\Application;
use PKP\config\Config;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\ReviewReminder;
use APP\plugins\generic\reviewReminder\lib\ICS;
use APP\plugins\generic\reviewReminder\classes\ReminderFile;

class ReviewReminderService
{
    private $context;
    private $submission;
    private $reviewAssignment;
    private $reviewer;
    private $reviewDueDate;
    private $oneClickReviewerUrl;

    private const EMAIL_TEMPLATE_KEY = 'REVIEW_REMINDER';

    public function __construct($context, $reviewAssignment, $reviewer, $reviewDueDate, $oneClickReviewerUrl)
    {
        $this->context = $context;
        $this->submission = Repo::submission()->get((int) $reviewAssignment->getSubmissionId());
        $this->reviewAssignment = $reviewAssignment;
        $this->reviewer = $reviewer;
        $this->reviewDueDate = $reviewDueDate;
        $this->oneClickReviewerUrl = $oneClickReviewerUrl;
    }

    public function sendReviewReminder()
    {
        $filePath = $this->createICalendarFile();
        $this->reviewAssignment->setDateDue($this->reviewDueDate);
        $this->reviewAssignment->setReviewerFullName($this->reviewer->getFullName());

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $this->context->getId(),
            self::EMAIL_TEMPLATE_KEY
        );
        $email = new ReviewReminder($this->context, $this->submission, $this->reviewAssignment);
        $email->from($this->context->getData('contactEmail'), $this->context->getData('contactEmail'))
            ->to([['name' => $this->reviewer->getFullName(), 'email' => $this->reviewer->getEmail()]])
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'))
            ->attach($filePath, ['as' => 'invite.ics']);

        Mail::send($email);
    }

    private function createICalendarFile()
    {
        $timeZone = new \DateTimeZone(Config::getVar('general', 'time_zone'));
        $reviewDueDateTime = new \DateTime($this->reviewDueDate, $timeZone);
        $reviewDueDateTime->setTime(23, 59, 59);
        $formattedReviewDueDate = $reviewDueDateTime->format('Ymd\THis\Z');

        $ics = new ICS(array(
            'description' => __(
                'plugins.generic.reviewReminder.ics.description',
                [
                    'submissionTitle' => $this->submission->getLocalizedTitle(),
                    'submissionReviewUrl' => $this->oneClickReviewerUrl ?? $this->getReviewUrl()
                ]
            ),
            'dtstart' => 'now',
            'dtend' => $formattedReviewDueDate,
            'summary' => __(
                'plugins.generic.reviewReminder.ics.summary',
                ['journalName' => $this->context->getLocalizedName()]
            ),
            'organizer' => $this->context->getLocalizedName() . ':mailto:' . $this->context->getData('contactEmail'),
        ));

        return ReminderFile::create($ics);
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

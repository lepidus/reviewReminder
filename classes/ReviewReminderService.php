<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\generic\reviewReminder\lib\ICS;
use APP\submission\Submission;
use Carbon\Carbon;
use PKP\config\Config;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewReminderService
{
    private Journal $context;
    private Submission $submission;
    private ReviewAssignment $reviewAssignment;

    public function __construct(Journal $context, ReviewAssignment $reviewAssignment)
    {
        $this->context = $context;
        $this->submission = Repo::submission()->get((int) $reviewAssignment->getSubmissionId());
        $this->reviewAssignment = $reviewAssignment;
    }

    public function getCalendarContents(): string
    {
        return ReminderFile::contents($this->createICalendar());
    }

    private function createICalendar(): ICS
    {
        $submissionTitle = $this->submission->getCurrentPublication()->getLocalizedTitle();
        $reviewDueDate = new \DateTimeImmutable($this->reviewAssignment->getDateDue());

        $ics = new ICS([
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
            'uid' => 'review-assignment-' . $this->reviewAssignment->getId() . '@' . $this->getHost(),
            'sequence' => Carbon::now()->getTimestamp(),
            'organizer' => [
                'name' => $this->context->getLocalizedName(),
                'email' => $this->context->getData('contactEmail'),
            ],
        ]);

        return $ics;
    }

    private function getHost(): string
    {
        return parse_url(Config::getVar('general', 'base_url'), PHP_URL_HOST) ?: 'localhost';
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

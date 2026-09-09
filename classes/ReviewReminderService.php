<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\generic\reviewReminder\lib\ICS;
use APP\submission\Submission;
use PKP\config\Config;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewReminderService
{
    private Journal $context;
    private Submission $submission;
    private ReviewAssignment $reviewAssignment;
    private ?string $reviewUrl;

    public function __construct(Journal $context, ReviewAssignment $reviewAssignment, ?string $reviewUrl)
    {
        $this->context = $context;
        $this->submission = Repo::submission()->get((int) $reviewAssignment->getSubmissionId());
        $this->reviewAssignment = $reviewAssignment;
        $this->reviewUrl = $reviewUrl;
    }

    public function getCalendarContents(): string
    {
        return ReminderFile::contents($this->createICalendar());
    }

    private function createICalendar(): ICS
    {
        $timeZone = new \DateTimeZone(Config::getVar('general', 'time_zone'));
        $reviewDueDateTime = new \DateTime($this->reviewAssignment->getDateDue(), $timeZone);
        $reviewDueDateTime->setTime(23, 59, 59);
        $formattedReviewDueDate = $reviewDueDateTime->format('Ymd\THis\Z');

        $ics = new ICS([
            'description' => __(
                'plugins.generic.reviewReminder.ics.description',
                [
                    'submissionTitle' => $this->escapeICalendarTitle(
                        $this->submission->getCurrentPublication()->getLocalizedTitle()
                    ),
                    'submissionReviewUrl' => $this->reviewUrl ?? $this->getReviewUrl()
                ]
            ),
            'dtstart' => 'now',
            'dtend' => $formattedReviewDueDate,
            'summary' => __(
                'plugins.generic.reviewReminder.ics.summary',
                ['journalName' => $this->context->getLocalizedName()]
            ),
            'organizer' => $this->context->getLocalizedName() . ':mailto:' . $this->context->getData('contactEmail'),
        ]);

        return $ics;
    }

    private function escapeICalendarTitle(string $title): string
    {
        return str_replace(
            ['\\', "\r\n", "\r", "\n"],
            ['\\\\', '\\n', '\\n', '\\n'],
            $title
        );
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

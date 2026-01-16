<?php

namespace APP\plugins\generic\reviewReminder\classes;

use DateTime;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;


class PendingReviewsEmailBuilder
{
    private $context;
    private $reviewer;
    private $submissions;
    private $locale;

    public function __construct($context, $reviewer, $submissions, $locale)
    {
        $this->context = $context;
        $this->reviewer = $reviewer;
        $this->submissions = $submissions;
        $this->locale = $locale;
    }

    public function buildEmail()
    {
        $emailTemplate = Repo::emailTemplate()->getByKey(
            $this->context->getId(),
            'PENDING_REVIEWS_REMINDER'
        );

        $submissionsString = $this->getSubmissionsString();

        $email = new PendingReviewsReminder($this->context, [
            'reviewerName' => $this->reviewer->getFullName(),
            'submissionsList' => $submissionsString
        ]);
        $email->from($this->context->getData('contactEmail'), $this->context->getData('contactName'));
        $email->to([['name' => $this->reviewer->getFullName(), 'email' => $this->reviewer->getEmail()]]);
        $email->subject($emailTemplate->getLocalizedData('subject'));
        $email->body($emailTemplate->getLocalizedData('body'));

        return $email;
    }

    private function getSubmissionsString(): string
    {
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $request->setDispatcher($dispatcher);

        $submissionsString = '';
        foreach ($this->submissions as $submissionData) {
            $submission = $submissionData['submission'];
            $url = $request->getDispatcher()->url(
                $request,
                Application::ROUTE_PAGE,
                $this->context->getData('urlPath'),
                'reviewer',
                'submission',
                null,
                [$submission->getId()]
            );

            $reviewDueDate = new DateTime($submissionData['reviewDueDate']);
            $reviewDueDate = $reviewDueDate->format($this->context->getLocalizedDateFormatShort($this->locale));

            $submissionString = "<p><a href=\"$url\">" . $submission->getLocalizedData('title', $this->locale) . '</a> - '
                . __('plugins.generic.reviewReminder.reviewDueDate', ['reviewDueDate' => $reviewDueDate], $this->locale) . '</p>';

            $submissionsString .= $submissionString;
        }

        return $submissionsString;
    }
}

<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewRemind;
use PKP\mail\mailables\ReviewRequest;
use PKP\mail\mailables\ReviewRequestSubsequent;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\mail\variables\SubmissionEmailVariable;
use PKP\plugins\Hook;
use PKP\submission\reviewAssignment\ReviewAssignment;

class HookCallbacks
{
    private ?ReviewAssignment $assignedReview = null;

    public function rememberAssignedReview(string $hookName, array $args): bool
    {
        $reviewAssignment = $args[0] ?? null;
        if ($reviewAssignment instanceof ReviewAssignment) {
            $this->assignedReview = $reviewAssignment;
        }

        return Hook::CONTINUE;
    }

    public function attachCalendarInvite(string $hookName, Mailable $mailable): bool
    {
        if (
            !($mailable instanceof ReviewRequest
                || $mailable instanceof ReviewRequestSubsequent
                || $mailable instanceof ReviewRemind)
        ) {
            return Hook::CONTINUE;
        }

        $reviewAssignment = $this->assignedReview ?? $this->getReviewAssignment($mailable);
        $this->assignedReview = null;
        if (!$reviewAssignment) {
            return Hook::CONTINUE;
        }

        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        if (!$submission) {
            return Hook::CONTINUE;
        }

        $context = app()->get('context')->get($submission->getData('contextId'));
        if (!$context) {
            return Hook::CONTINUE;
        }

        $reviewUrl = $mailable->viewData[ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL] ?? null;
        $reviewReminderService = new ReviewReminderService($context, $reviewAssignment, $reviewUrl);

        $mailable->attachData(
            $reviewReminderService->getCalendarContents(),
            'invite.ics',
            ['mime' => 'text/calendar']
        );
        return Hook::CONTINUE;
    }

    private function getReviewAssignment(Mailable $mailable): ?ReviewAssignment
    {
        $submissionId = (int) ($mailable->viewData[SubmissionEmailVariable::SUBMISSION_ID] ?? 0);
        $reviewerEmail = $mailable->to[0]['address'] ?? null;
        if (!$submissionId || !$reviewerEmail) {
            return null;
        }

        $reviewer = Repo::user()->getByEmail($reviewerEmail, true);
        if (!$reviewer) {
            return null;
        }

        $locale = $mailable->getLocale() ?? Locale::getLocale();
        $expectedValues = array_intersect_key(
            $mailable->viewData,
            array_flip([
                ReviewAssignmentEmailVariable::REVIEW_DUE_DATE,
                ReviewAssignmentEmailVariable::REVIEW_ROUND,
                ReviewAssignmentEmailVariable::REVIEWER_NAME,
            ])
        );

        $reviewAssignments = Repo::reviewAssignment()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewer->getId()])
            ->getMany();

        foreach ($reviewAssignments as $reviewAssignment) {
            $values = (new ReviewAssignmentEmailVariable($reviewAssignment, $mailable))->values($locale);
            if ($expectedValues === array_intersect_key($values, $expectedValues)) {
                return $reviewAssignment;
            }
        }

        return null;
    }
}

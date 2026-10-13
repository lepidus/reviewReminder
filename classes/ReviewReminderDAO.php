<?php

namespace APP\plugins\generic\reviewReminder\classes;

use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\submission\PKPSubmission;
use Illuminate\Support\Facades\DB;

class ReviewReminderDAO extends DAO
{
    public function getIncompleteReviewsByReviewer(int $reviewerId, int $contextId)
    {
        $results = DB::table('review_assignments as r')
            ->leftJoin('review_rounds as r2', 'r.review_round_id', '=', 'r2.review_round_id')
            ->join('submissions as s', 'r.submission_id', '=', 's.submission_id')
            ->select('r.*', 'r2.review_revision')
            ->where('r.reviewer_id', '=', $reviewerId)
            ->where('s.context_id', '=', $contextId)
            ->where('s.status', '=', PKPSubmission::STATUS_QUEUED)
            ->whereColumn('s.stage_id', '=', 'r.stage_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('review_rounds as newer_round')
                    ->whereColumn('newer_round.submission_id', '=', 'r.submission_id')
                    ->whereColumn('newer_round.stage_id', '=', 'r.stage_id')
                    ->whereColumn('newer_round.round', '>', 'r.round');
            })
            ->whereNotNull('r.date_notified')
            ->whereNull('r.date_completed')
            ->where('r.declined', '!=', 1)
            ->where('r.cancelled', '!=', 1)
            ->orderBy('r.submission_id')
            ->get();

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignments = [];

        foreach ($results as $row) {
            $reviewAssignments[] = $reviewAssignmentDao->_fromRow((array) $row);
        }

        return $reviewAssignments;
    }
}

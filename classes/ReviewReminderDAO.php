<?php

namespace APP\plugins\generic\reviewReminder\classes;

use PKP\db\DAO;
use PKP\db\DAORegistry;
use Illuminate\Support\Facades\DB;

class ReviewReminderDAO extends DAO
{
    public function getIncompleteReviewsByReviewer(int $reviewerId)
    {
        $results = DB::table('review_assignments as r')
            ->leftJoin('review_rounds as r2', 'r.review_round_id', '=', 'r2.review_round_id')
            ->select('r.*', 'r2.review_revision')
            ->where('r.reviewer_id', '=', $reviewerId)
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

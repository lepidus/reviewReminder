<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\facades\Repo;
use Illuminate\Support\LazyCollection;

class ReviewReminderDAO
{
    public function getIncompleteReviewsByContext(int $contextId): LazyCollection
    {
        return Repo::reviewAssignment()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByIsIncomplete(true)
            ->orderBySubmissionId()
            ->getMany();
    }
}

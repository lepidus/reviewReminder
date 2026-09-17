<?php

use APP\plugins\generic\reviewReminder\classes\ReviewReminderDAO;
use Illuminate\Support\LazyCollection;
use PKP\submission\reviewAssignment\Collector;
use PKP\submission\reviewAssignment\Repository as ReviewAssignmentRepository;
use PKP\tests\PKPTestCase;

class ReviewReminderDAOTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), ReviewAssignmentRepository::class];
    }

    public function testGetsOnlyCurrentContextIncompleteReviews(): void
    {
        $reviews = LazyCollection::make([]);
        $collector = $this->createMock(Collector::class);
        $collector->expects($this->once())->method('filterByContextIds')->with([10])->willReturnSelf();
        $collector->expects($this->once())->method('filterByIsIncomplete')->with(true)->willReturnSelf();
        $collector->expects($this->once())->method('orderBySubmissionId')->willReturnSelf();
        $collector->expects($this->once())->method('getMany')->willReturn($reviews);

        $repository = $this->createMock(ReviewAssignmentRepository::class);
        $repository->expects($this->once())->method('getCollector')->willReturn($collector);
        app()->instance(ReviewAssignmentRepository::class, $repository);

        $this->assertSame($reviews, (new ReviewReminderDAO())->getIncompleteReviewsByContext(10));
    }
}

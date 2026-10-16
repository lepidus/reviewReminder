<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\classes\HookCallbacks;
use APP\plugins\generic\reviewReminder\classes\mail\mailables\ReviewReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\ReviewerAction;
use PKP\tests\PKPTestCase;

class HookCallbacksTest extends PKPTestCase
{
    private const CONTEXT_PATH = 'publicknowledge';
    private const SUBMISSION_ID = 7;
    private const REVIEWER_ID = 9;

    private $reviewAssignmentDao;
    private $confirmedReviewAssignmentIds = [];

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::table('access_keys')->whereIn('assoc_id', $this->confirmedReviewAssignmentIds)->delete();

        parent::tearDown();
    }

    private function createContextRequest(): PKPRequest
    {
        Registry::delete('request');
        $_SERVER['PATH_INFO'] = self::CONTEXT_PATH . '/index/index';
        $application = Application::get();
        $request = $application->getRequest();

        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $request->setRouter($router);

        return $request;
    }

    private function getCurrentReviewAssignment(int $reviewerId): ReviewAssignment
    {
        return $this->reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer(self::SUBMISSION_ID, $reviewerId);
    }

    private function confirmReview(ReviewAssignment $reviewAssignment): void
    {
        $this->confirmedReviewAssignmentIds[] = $reviewAssignment->getId();
        $submission = Repo::submission()->get(self::SUBMISSION_ID);
        $mailable = (new ReviewerAction())->getResponseEmail($submission, $reviewAssignment, false, null);
        $request = $this->createContextRequest();

        (new HookCallbacks())->sendReviewReminder('ReviewerAction::confirmReview', [$request, $submission, $mailable, false]);
    }

    private function getSentReminder(): ReviewReminder
    {
        $sentReminders = Mail::sent(ReviewReminder::class);
        $this->assertCount(1, $sentReminders);
        return $sentReminders->first();
    }

    public function testShouldAttachCalendarInviteWithoutWritingFileToDisk(): void
    {
        $this->confirmReview($this->getCurrentReviewAssignment(self::REVIEWER_ID));

        $reminder = $this->getSentReminder();
        $this->assertEmpty($reminder->attachments);
        $this->assertCount(1, $reminder->rawAttachments);
        $this->assertEquals('invite.ics', $reminder->rawAttachments[0]['name']);
        $this->assertEquals('text/calendar', $reminder->rawAttachments[0]['options']['mime']);
        $this->assertStringStartsWith('BEGIN:VCALENDAR', $reminder->rawAttachments[0]['data']);
    }
}

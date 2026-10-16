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
    private const NAMESAKE_REVIEWER_ID = 10;
    private const NEW_ROUND_DUE_DATE = '2027-03-15 00:00:00';
    private const NEW_ROUND_CALENDAR_END = 'DTEND:20270315T235959';

    private $reviewAssignmentDao;
    private $newReviewRoundId;
    private $newReviewAssignmentId;
    private $originalNamesOfRenamedReviewer = [];
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

        if ($this->newReviewAssignmentId) {
            DB::table('review_assignments')->where('review_id', $this->newReviewAssignmentId)->delete();
        }

        if ($this->newReviewRoundId) {
            DB::table('review_rounds')->where('review_round_id', $this->newReviewRoundId)->delete();
        }

        foreach ($this->originalNamesOfRenamedReviewer as $originalName) {
            DB::table('user_settings')
                ->where('user_id', $originalName->user_id)
                ->where('locale', $originalName->locale)
                ->where('setting_name', $originalName->setting_name)
                ->update(['setting_value' => $originalName->setting_value]);
        }

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

    private function assignReviewerToNewReviewRound(int $reviewerId): ReviewAssignment
    {
        $currentAssignment = $this->getCurrentReviewAssignment($reviewerId);
        $this->newReviewRoundId = DB::table('review_rounds')->insertGetId([
            'submission_id' => self::SUBMISSION_ID,
            'stage_id' => $currentAssignment->getStageId(),
            'round' => $currentAssignment->getRound() + 1,
            'status' => 0,
        ], 'review_round_id');

        $newAssignment = clone $currentAssignment;
        $newAssignment->setReviewRoundId($this->newReviewRoundId);
        $newAssignment->setRound($currentAssignment->getRound() + 1);
        $newAssignment->setDateDue(self::NEW_ROUND_DUE_DATE);
        $newAssignment->setDateConfirmed(null);
        $this->reviewAssignmentDao->insertObject($newAssignment);
        $this->newReviewAssignmentId = $newAssignment->getId();

        return $this->reviewAssignmentDao->getById($this->newReviewAssignmentId);
    }

    private function giveReviewerTheNameOf(int $reviewerId, int $namesakeId): void
    {
        $this->originalNamesOfRenamedReviewer = DB::table('user_settings')
            ->where('user_id', $reviewerId)
            ->whereIn('setting_name', ['givenName', 'familyName'])
            ->get()
            ->all();

        $namesakeNames = DB::table('user_settings')
            ->where('user_id', $namesakeId)
            ->whereIn('setting_name', ['givenName', 'familyName'])
            ->get();
        foreach ($namesakeNames as $namesakeName) {
            DB::table('user_settings')
                ->where('user_id', $reviewerId)
                ->where('locale', $namesakeName->locale)
                ->where('setting_name', $namesakeName->setting_name)
                ->update(['setting_value' => $namesakeName->setting_value]);
        }
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

    public function testShouldRemindDueDateOfReviewRoundBeingAccepted(): void
    {
        $newRoundAssignment = $this->assignReviewerToNewReviewRound(self::REVIEWER_ID);

        $this->confirmReview($newRoundAssignment);

        $calendarInvite = $this->getSentReminder()->rawAttachments[0];
        $this->assertStringContainsString(self::NEW_ROUND_CALENDAR_END, $calendarInvite['data']);
    }

    public function testShouldRemindReviewerWhoAcceptedWhenAnotherReviewerHasTheSameName(): void
    {
        $this->giveReviewerTheNameOf(self::REVIEWER_ID, self::NAMESAKE_REVIEWER_ID);
        $namesake = Repo::user()->get(self::NAMESAKE_REVIEWER_ID);

        $this->confirmReview($this->getCurrentReviewAssignment(self::NAMESAKE_REVIEWER_ID));

        $this->assertEquals($namesake->getEmail(), $this->getSentReminder()->to[0]['address']);
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

<?php

use APP\plugins\generic\reviewReminder\classes\mail\mailables\PendingReviewsReminder;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class PendingReviewsReminderTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Locale::registerPath('plugins/generic/reviewReminder/locale');
    }

    public function testShouldDescribeVariablesOfTheWeeklyReminder(): void
    {
        $descriptions = PendingReviewsReminder::getDataDescriptions();

        $this->assertEquals("The reviewer's full name", $descriptions['reviewerName']);
        $this->assertEquals('The pending reviews, each with a link to the review and its due date', $descriptions['submissionsList']);
        $this->assertArrayHasKey('journalName', $descriptions);
    }

    public function testShouldBeSentBySystemToReviewers(): void
    {
        $this->assertEquals([Mailable::FROM_SYSTEM], PendingReviewsReminder::getFromRoleIds());
        $this->assertEquals([Role::ROLE_ID_REVIEWER], PendingReviewsReminder::getToRoleIds());
        $this->assertEquals([Mailable::GROUP_REVIEW], PendingReviewsReminder::getGroupIds());
    }
}

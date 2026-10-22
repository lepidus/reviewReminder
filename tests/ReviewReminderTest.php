<?php

use APP\plugins\generic\reviewReminder\classes\mail\mailables\ReviewReminder;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class ReviewReminderTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Locale::registerPath('plugins/generic/reviewReminder/locale');
    }

    public function testShouldBeNamedApartFromTheOjsReviewReminder(): void
    {
        $this->assertEquals('Review Acceptance Reminder', ReviewReminder::getName());
    }

    public function testShouldDescribeVariablesUsedByTheAcceptanceReminder(): void
    {
        $descriptions = ReviewReminder::getDataDescriptions();

        foreach (['reviewerName', 'journalName', 'submissionTitle', 'reviewDueDate', 'reviewAssignmentUrl'] as $variable) {
            $this->assertArrayHasKey($variable, $descriptions);
        }
    }

    public function testShouldBeSentBySystemToReviewers(): void
    {
        $this->assertEquals([Mailable::FROM_SYSTEM], ReviewReminder::getFromRoleIds());
        $this->assertEquals([Role::ROLE_ID_REVIEWER], ReviewReminder::getToRoleIds());
        $this->assertEquals([Mailable::GROUP_REVIEW], ReviewReminder::getGroupIds());
    }
}

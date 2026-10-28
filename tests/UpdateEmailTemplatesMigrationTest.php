<?php

use APP\plugins\generic\reviewReminder\classes\migrations\UpdateEmailTemplatesMigration;
use Illuminate\Support\Facades\DB;
use PKP\tests\PKPTestCase;

class UpdateEmailTemplatesMigrationTest extends PKPTestCase
{
    private const CONTEXT_ID = 1;
    private const REMOVED_TEMPLATE_KEY = 'REVIEW_REMINDER';
    private const WEEKLY_TEMPLATE_KEY = 'PENDING_REVIEWS_REMINDER';

    private $customizedTemplateId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('email_templates_default_data')->insert([
            'email_key' => self::REMOVED_TEMPLATE_KEY,
            'locale' => 'en',
            'name' => 'Review Reminder',
            'subject' => 'Review Reminder',
            'body' => 'You have a pending review.',
        ]);
        $this->customizedTemplateId = DB::table('email_templates')->insertGetId([
            'email_key' => self::REMOVED_TEMPLATE_KEY,
            'context_id' => self::CONTEXT_ID,
        ], 'email_id');
        DB::table('email_templates_settings')->insert([
            'email_id' => $this->customizedTemplateId,
            'locale' => 'en',
            'setting_name' => 'subject',
            'setting_value' => 'Customized review reminder',
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('email_templates_settings')->where('email_id', $this->customizedTemplateId)->delete();
        DB::table('email_templates')->where('email_key', self::REMOVED_TEMPLATE_KEY)->delete();
        DB::table('email_templates_default_data')->where('email_key', self::REMOVED_TEMPLATE_KEY)->delete();
        parent::tearDown();
    }

    public function testShouldRemoveTemplateOfTheFormerReviewReminderEmail(): void
    {
        (new UpdateEmailTemplatesMigration())->up();

        $this->assertFalse(DB::table('email_templates_default_data')->where('email_key', self::REMOVED_TEMPLATE_KEY)->exists());
        $this->assertFalse(DB::table('email_templates')->where('email_key', self::REMOVED_TEMPLATE_KEY)->exists());
        $this->assertFalse(DB::table('email_templates_settings')->where('email_id', $this->customizedTemplateId)->exists());
    }

    public function testShouldKeepTemplateOfTheWeeklyReminder(): void
    {
        (new UpdateEmailTemplatesMigration())->up();

        $this->assertTrue(DB::table('email_templates_default_data')->where('email_key', self::WEEKLY_TEMPLATE_KEY)->where('locale', 'en')->exists());
    }
}

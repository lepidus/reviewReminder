<?php

use APP\plugins\generic\reviewReminder\classes\migrations\UpdateEmailTemplatesMigration;
use Illuminate\Support\Facades\DB;
use PKP\tests\PKPTestCase;

class UpdateEmailTemplatesMigrationTest extends PKPTestCase
{
    private const ACCEPTANCE_TEMPLATE_KEY = 'REVIEW_REMINDER';

    private $defaultDataBeforeTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultDataBeforeTest = DB::table('email_templates_default_data')
            ->where('email_key', self::ACCEPTANCE_TEMPLATE_KEY)
            ->get();
    }

    protected function tearDown(): void
    {
        DB::table('email_templates_default_data')->where('email_key', self::ACCEPTANCE_TEMPLATE_KEY)->delete();
        DB::table('email_templates_default_data')->insert($this->defaultDataBeforeTest->map(fn ($row) => (array) $row)->all());
        parent::tearDown();
    }

    public function testShouldRenameTheAcceptanceReminderTemplateOnUpgrade(): void
    {
        DB::table('email_templates_default_data')
            ->where('email_key', self::ACCEPTANCE_TEMPLATE_KEY)
            ->where('locale', 'en')
            ->update(['name' => 'Review Reminder']);

        (new UpdateEmailTemplatesMigration())->up();

        $this->assertEquals(
            'Review Acceptance Reminder',
            DB::table('email_templates_default_data')
                ->where('email_key', self::ACCEPTANCE_TEMPLATE_KEY)
                ->where('locale', 'en')
                ->value('name')
        );
    }
}

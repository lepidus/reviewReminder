<?php

namespace APP\plugins\generic\reviewReminder\tests;

use APP\plugins\generic\reviewReminder\classes\ReminderFile;
use APP\plugins\generic\reviewReminder\lib\ICS;
use PKP\tests\PKPTestCase;

class ReminderFileTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testReminderFileCreation(): void
    {
        $ics = new ICS([
            'description' => 'Description event mock.',
            'dtstart' => '2024-07-12',
            'dtend' => '2024-07-30',
            'summary' => 'Reviewer Reminder'
        ]);
        $filePath = ReminderFile::create($ics);
        $this->assertMatchesRegularExpression('/\/tmp\/reviewReminder.+\/invite\.ics/', $filePath);
    }

    public function testReminderFileContents(): void
    {
        $ics = new ICS([
            'description' => 'Description event mock.',
            'dtstart' => '2024-07-12',
            'dtend' => '2024-07-30',
            'summary' => 'Reviewer Reminder'
        ]);

        $contents = ReminderFile::contents($ics);

        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $contents);
        $this->assertStringContainsString("SUMMARY:Reviewer Reminder\r\n", $contents);
        $this->assertStringContainsString('END:VCALENDAR', $contents);
    }
}

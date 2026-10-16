<?php

namespace APP\plugins\generic\reviewReminder\tests;

use PKP\tests\PKPTestCase;
use APP\plugins\generic\reviewReminder\classes\ReminderFile;
use APP\plugins\generic\reviewReminder\lib\ICS;

class ReminderFileTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testReminderFileContents(): void
    {
        $ics = new ICS([
            'description' => "Description event mock.",
            'dtstart' => '2024-07-12',
            'dtend' => '2024-07-30',
            'summary' => "Reviewer Reminder"
        ]);
        $contents = ReminderFile::contents($ics);
        $this->assertStringStartsWith('BEGIN:VCALENDAR', $contents);
        $this->assertStringContainsString('SUMMARY:Reviewer Reminder', $contents);
        $this->assertStringEndsWith('END:VCALENDAR', $contents);
    }
}

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

    public function testReminderFileCreation(): void
    {
        $ics = new ICS(array(
            'description' => "Description event mock.",
            'dtstart' => '2024-07-12',
            'dtend' => '2024-07-30',
            'summary' => "Reviewer Reminer"
        ));
        $filePath = ReminderFile::create($ics);
        $this->assertMatchesRegularExpression('/\/tmp\/reviewReminder.+\/invite\.ics/', $filePath);
    }
}

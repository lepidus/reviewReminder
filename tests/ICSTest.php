<?php

namespace APP\plugins\generic\reviewReminder\tests;

use APP\plugins\generic\reviewReminder\lib\ICS;
use DateTime;
use DateTimeZone;
use PKP\tests\PKPTestCase;

class ICSTest extends PKPTestCase
{
    private const MAX_LINE_OCTETS = 75;

    private function getLines(array $properties): array
    {
        return explode("\r\n", (new ICS($properties))->to_string());
    }

    private function getUnfoldedLines(array $properties): array
    {
        return explode("\r\n", str_replace("\r\n ", '', (new ICS($properties))->to_string()));
    }

    public function testShouldIdentifyPluginAsCalendarProducer(): void
    {
        $this->assertContains('PRODID:-//Lepidus Tecnologia//Review Reminder//EN', $this->getLines([]));
    }

    public function testShouldNotDeclareTimeZone(): void
    {
        $this->assertStringNotContainsString('VTIMEZONE', (new ICS([]))->to_string());
    }

    public function testShouldWriteTimestampInUtc(): void
    {
        $timestamps = preg_grep('/^DTSTAMP:/', $this->getLines([]));

        $this->assertCount(1, $timestamps);
        $this->assertMatchesRegularExpression('/^DTSTAMP:\d{8}T\d{6}Z$/', reset($timestamps));
        $timestamp = DateTime::createFromFormat('Ymd\THis\Z', substr(reset($timestamps), strlen('DTSTAMP:')), new DateTimeZone('+00:00'));
        $this->assertEqualsWithDelta(time(), $timestamp->getTimestamp(), 5);
    }

    public function testShouldQuoteOrganizerNameContainingSeparators(): void
    {
        $lines = $this->getLines(['organizer' => ['name' => 'Journal; of, "Public": Knowledge', 'email' => 'contact@journal.org']]);

        $this->assertContains('ORGANIZER;CN="Journal; of, Public: Knowledge":mailto:contact@journal.org', $lines);
    }

    public function testShouldEscapeSeparatorsAndLineBreaksInText(): void
    {
        $lines = $this->getLines(['summary' => "Title, with; separators\\ and\r\nline break"]);

        $this->assertContains('SUMMARY:Title\, with\; separators\\\\ and\nline break', $lines);
    }

    public function testShouldFoldLinesLongerThan75Octets(): void
    {
        $description = str_repeat('Avaliação de submissão ', 10);

        $lines = $this->getLines(['description' => $description]);

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(self::MAX_LINE_OCTETS, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
        $this->assertContains('DESCRIPTION:' . $description, $this->getUnfoldedLines(['description' => $description]));
    }
}

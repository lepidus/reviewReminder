<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\plugins\generic\reviewReminder\lib\ICS;
use PKP\config\Config;

class ReminderFile
{
    public static function contents(ICS $ics): string
    {
        $timeZone = Config::getVar('general', 'time_zone');
        return $ics->to_string($timeZone);
    }

    public static function create(ICS $ics): string
    {
        $packageDirPath = tempnam('/tmp', 'reviewReminder');
        unlink($packageDirPath);
        mkdir($packageDirPath);
        $filePath = $packageDirPath . DIRECTORY_SEPARATOR . 'invite.ics';
        $reviewerReminderCalendarFile = fopen($filePath, 'w');
        fwrite($reviewerReminderCalendarFile, self::contents($ics));
        fclose($reviewerReminderCalendarFile);

        return $filePath;
    }
}

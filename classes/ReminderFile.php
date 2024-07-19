<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\plugins\generic\reviewReminder\lib\ICS;
use PKP\config\Config;

class ReminderFile
{
    public static function create(ICS $ics): string
    {
        $packageDirPath = tempnam('/tmp', 'reviewReminder');
        unlink($packageDirPath);
        mkdir($packageDirPath);
        $filePath = $packageDirPath . DIRECTORY_SEPARATOR . 'invite.ics';
        $reviewerReminderCalendarFile = fopen($filePath, 'w');
        $timeZone = Config::getVar('general', 'time_zone');
        fwrite($reviewerReminderCalendarFile, $ics->to_string($timeZone));
        fclose($reviewerReminderCalendarFile);

        return $filePath;
    }
}

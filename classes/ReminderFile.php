<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\plugins\generic\reviewReminder\lib\ICS;

class ReminderFile
{
    public static function create(ICS $ics): string
    {
        $packageDirPath = tempnam('/tmp', 'reviewReminder');
        unlink($packageDirPath);
        mkdir($packageDirPath);
        $filePath = $packageDirPath . DIRECTORY_SEPARATOR . 'invite.ics';
        $reviewerReminderCalendarFile = fopen($filePath, 'w');
        fwrite($reviewerReminderCalendarFile, $ics->to_string());
        fclose($reviewerReminderCalendarFile);

        return $filePath;
    }
}

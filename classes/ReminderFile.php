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
}

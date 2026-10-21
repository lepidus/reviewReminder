<?php

namespace APP\plugins\generic\reviewReminder\classes;

use APP\plugins\generic\reviewReminder\lib\ICS;

class ReminderFile
{
    public static function contents(ICS $ics): string
    {
        return $ics->to_string();
    }
}

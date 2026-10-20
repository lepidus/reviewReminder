<?php

use APP\plugins\generic\reviewReminder\classes\tasks\SendPendingReviewsReminder;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use APP\scheduler\Scheduler;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PKP\tests\PKPTestCase;

class ReviewReminderPluginTest extends PKPTestCase
{
    private const WEEKLY_ON_MONDAY = '0 0 * * 1';

    public function testShouldScheduleWeeklyPendingReviewsReminder(): void
    {
        $schedule = new Schedule();

        (new ReviewReminderPlugin())->registerSchedules(new Scheduler($schedule));

        $pendingReviewsReminders = collect($schedule->events())
            ->filter(fn (Event $event) => $event->getSummaryForDisplay() == SendPendingReviewsReminder::class)
            ->values();
        $this->assertCount(1, $pendingReviewsReminders);
        $this->assertEquals(self::WEEKLY_ON_MONDAY, $pendingReviewsReminders[0]->expression);
    }
}

<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\SendUpcomingExamsMail::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Pokreni komandu svaki dan u 08:00
        $schedule->command('emails:send-upcoming-exams')->everyMinute();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}

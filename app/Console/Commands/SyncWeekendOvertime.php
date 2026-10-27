<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncWeekendOvertime extends Command
{
    protected $signature = 'app:sync-weekend-overtime';

    protected $description = 'Upisuje prekovremene za subotu i nedjelju koji nisu redovan rad i radnik nema stimulaciju.';

    public function handle(): int
    {
        $booked = 0;

        AttendanceRecord::query()
            ->whereNotNull('entry_time')
            ->orderBy('id')
            ->chunkById(200, function ($records) use (&$booked) {
                $records->load(['employee:id,dept,stimulacija', 'employee.department']);

                foreach ($records as $record) {
                    $start = $record->effective_start ?: $record->entry_time;
                    if (!$start || !Carbon::parse($start)->timezone(config('app.timezone'))->isWeekend()) {
                        continue;
                    }

                    if (!$record->isBookedAsWeekendOvertime()) {
                        continue;
                    }

                    $record->refreshOvertimeBooking();
                    $booked++;
                }
            });

        $this->info('Vikend prijava upisanih u prekovremene: ' . $booked);

        return self::SUCCESS;
    }
}

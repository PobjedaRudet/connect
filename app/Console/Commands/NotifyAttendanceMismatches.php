<?php

namespace App\Console\Commands;

use App\Services\AttendanceMismatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyAttendanceMismatches extends Command
{
    protected $signature = 'app:notify-attendance-mismatches {--date=}';

    protected $description = 'Evidentira neslaganja dolaska i odlaska. Mail ide u 06:30.';

    public function handle(AttendanceMismatchService $service): int
    {
        $tz = config('app.timezone');
        $dateOption = $this->option('date');

        if (is_string($dateOption) && $dateOption !== '') {
            try {
                $day = Carbon::createFromFormat('!Y-m-d', $dateOption, $tz);
            } catch (\Throwable) {
                $day = false;
            }
            if ($day === false || $day->format('Y-m-d') !== $dateOption) {
                $this->error('Datum mora biti u formatu YYYY-MM-DD.');

                return self::FAILURE;
            }
            $day = $day->startOfDay();
            $windows = [[
                'from' => $day->copy(),
                'until' => $day->copy()->endOfDay(),
                'flag_missing_exit' => true,
            ]];
        } else {
            $today = Carbon::now($tz)->startOfDay();
            $untilMorning = $today->copy()->setTime(8, 0);
            $yesterday = $today->copy()->subDay();
            $windows = [
                [
                    'from' => $yesterday->copy(),
                    'until' => $yesterday->copy()->endOfDay(),
                    'flag_missing_exit' => true,
                ],
                [
                    'from' => $today->copy(),
                    'until' => $untilMorning,
                    'flag_missing_exit' => false,
                ],
            ];
        }

        $created = $service->recordWindows($windows);

        $this->info('Nova neslaganja: ' . $created->count() . '. Mail ide u 06:30.');

        return self::SUCCESS;
    }
}

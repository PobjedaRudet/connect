<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SihtericaController extends Controller
{
    public function index(Request $request)
    {
        $monthParam = (string) $request->query('month', '');
        $monthParam = trim($monthParam);

        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = Carbon::now(config('app.timezone'))->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $monthParam, config('app.timezone'))->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $dayNames = [
            1 => 'Pon',
            2 => 'Uto',
            3 => 'Sri',
            4 => 'Čet',
            5 => 'Pet',
            6 => 'Sub',
            7 => 'Ned',
        ];

        $days = [];
        for ($day = 1; $day <= $monthStart->daysInMonth; $day++) {
            $date = $monthStart->copy()->day($day);
            $days[] = [
                'date' => $date->toDateString(),
                'day' => $day,
                'weekday' => $dayNames[$date->dayOfWeekIso] ?? '',
                'isWeekend' => $date->isWeekend(),
            ];
        }

        $employees = Employee::query()
            ->where('Active', true)
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get(['id', 'empID', 'firstName', 'lastName']);

        $records = AttendanceRecord::query()
            ->whereBetween('entry_time', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->orderBy('employee_id')
            ->orderBy('entry_time')
            ->get(['id', 'employee_id', 'entry_time', 'effective_start', 'exit_time', 'late_flag', 'status']);

        $attendance = [];
        foreach ($records as $record) {
            if (!$record->entry_time) {
                continue;
            }

            $employeeId = (int) $record->employee_id;
            $dateKey = $record->entry_time->toDateString();

            $existing = $attendance[$employeeId][$dateKey] ?? null;
            if ($existing && !empty($existing['entry_time_raw'])) {
                $existingTs = (int) $existing['entry_time_raw'];
                $currentTs = (int) $record->entry_time->getTimestamp();
                if ($currentTs >= $existingTs) {
                    continue;
                }
            }

            $attendance[$employeeId][$dateKey] = [
                'record_id' => (int) $record->id,
                'entry_time' => $record->entry_time?->timezone(config('app.timezone'))->format('H:i'),
                'exit_time' => $record->exit_time?->timezone(config('app.timezone'))->format('H:i'),
                'late_flag' => $record->late_flag,
                'status' => $record->status,
                'entry_time_raw' => $record->entry_time?->getTimestamp(),
            ];
        }

        // Strip raw timestamps from the response payload
        foreach ($attendance as $employeeId => $byDate) {
            foreach ($byDate as $dateKey => $payload) {
                unset($attendance[$employeeId][$dateKey]['entry_time_raw']);
            }
        }

        return Inertia::render('HR/Sihterica', [
            'month' => $monthParam,
            'days' => $days,
            'employees' => $employees->map(fn ($e) => [
                'id' => (int) $e->id,
                'empID' => (int) $e->empID,
                'full_name' => trim((string) $e->lastName . ' ' . (string) $e->firstName),
            ])->values()->all(),
            'attendance' => $attendance,
        ]);
    }
}

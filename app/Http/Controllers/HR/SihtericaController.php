<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Shift;
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

        $userId = $request->user()?->id;

        $employees = Employee::query()
            ->where('Active', true)
            ->when($userId, function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    // Cover both integer and string JSON storage
                    $q->whereJsonContains('nadlezne_osobe', (int) $userId)
                      ->orWhereJsonContains('nadlezne_osobe', (string) $userId);
                });
            })
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get(['id', 'empID', 'firstName', 'lastName']);

        $records = AttendanceRecord::query()
            ->with(['shift:id,name,start_time,end_time,attendance_credit_code'])
            ->whereBetween('entry_time', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->orderBy('employee_id')
            ->orderBy('entry_time')
            ->get([
                'id',
                'employee_id',
                'shift_id',
                'entry_time',
                'effective_start',
                'exit_time',
                'duration_minutes',
                'late_flag',
                'status',
            ]);

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
                'duration_minutes' => $record->duration_minutes,
                'duration_display' => $this->resolveDurationDisplay($record),
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

    private function resolveDurationDisplay(AttendanceRecord $record): ?string
    {
        $shift = $record->shift;
        if (!$shift) {
            return null;
        }

        $code = $shift->attendance_credit_code;
        if (!is_string($code) || trim($code) === '') {
            return null;
        }

        $code = strtoupper(trim($code));
        if (!in_array($code, ['II', 'III'], true)) {
            return null;
        }

        // "Došao na vrijeme" => nije zakasnio
        if (!empty($record->late_flag)) {
            return null;
        }

        // "Otišao na vrijeme" => exit_time >= kraj smjene
        if (!$record->entry_time || !$record->exit_time) {
            return null;
        }
        if (!$shift->start_time || !$shift->end_time) {
            return null;
        }

        $tz = config('app.timezone');

        $workDate = Carbon::parse($record->entry_time)->timezone($tz)->toDateString();

        $startTimeStr = $shift->start_time instanceof Carbon
            ? $shift->start_time->format('H:i:s')
            : (string) $shift->start_time;

        $endTimeStr = $shift->end_time instanceof Carbon
            ? $shift->end_time->format('H:i:s')
            : (string) $shift->end_time;

        try {
            $shiftStart = Carbon::parse($workDate . ' ' . $startTimeStr, $tz);
            $shiftEnd = Carbon::parse($workDate . ' ' . $endTimeStr, $tz);
        } catch (\Throwable $e) {
            return null;
        }

        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        $exit = Carbon::parse($record->exit_time)->timezone($tz);
        if ($exit->lessThan($shiftEnd)) {
            return null;
        }

        return $code;
    }
}

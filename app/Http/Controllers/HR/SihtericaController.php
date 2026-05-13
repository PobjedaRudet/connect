<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDayStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SihtericaController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

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

        $employees = $this->scopedEmployeeQuery($request->user())
            ->get(['id', 'empID', 'firstName', 'lastName']);

        $employeeIds = $employees->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

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
                'terminal_in',
                'terminal_out',
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
                'terminal_in' => $record->terminal_in,
                'terminal_out' => $record->terminal_out,
                'manual_status' => false,
                'manual_note' => null,
                'entry_time_raw' => $record->entry_time?->getTimestamp(),
            ];
        }

        if (!empty($employeeIds) && Schema::hasTable('attendance_day_statuses')) {
            $manualStatuses = AttendanceDayStatus::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('work_date', [
                    $monthStart->copy()->toDateString(),
                    $monthEnd->copy()->toDateString(),
                ])
                ->orderBy('work_date')
                ->get(['employee_id', 'work_date', 'status_code', 'note']);

            foreach ($manualStatuses as $manualStatus) {
                $employeeId = (int) $manualStatus->employee_id;
                $dateKey = $manualStatus->work_date?->toDateString();

                if (!$dateKey) {
                    continue;
                }

                if (isset($attendance[$employeeId][$dateKey])) {
                    continue;
                }

                $attendance[$employeeId][$dateKey] = [
                    'record_id' => null,
                    'entry_time' => null,
                    'exit_time' => null,
                    'duration_minutes' => null,
                    'duration_display' => strtoupper((string) $manualStatus->status_code),
                    'late_flag' => null,
                    'status' => null,
                    'terminal_in' => null,
                    'terminal_out' => null,
                    'manual_status' => true,
                    'manual_note' => $manualStatus->note,
                    'entry_time_raw' => null,
                ];
            }
        }

        // Strip raw timestamps from the response payload
        foreach ($attendance as $employeeId => $byDate) {
            foreach ($byDate as $dateKey => $payload) {
                unset($attendance[$employeeId][$dateKey]['entry_time_raw']);
            }
        }

        $shifts = Shift::orderBy('name')->get(['id', 'name', 'start_time', 'end_time']);

        return Inertia::render('HR/Sihterica', [
            'month' => $monthParam,
            'days' => $days,
            'employees' => $employees->map(fn ($e) => [
                'id' => (int) $e->id,
                'empID' => (int) $e->empID,
                'full_name' => trim((string) $e->lastName . ' ' . (string) $e->firstName),
            ])->values()->all(),
            'attendance' => $attendance,
            'shifts' => $shifts,
        ]);
    }

    public function manualStore(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id'    => 'nullable|exists:shifts,id',
            'date'        => 'required|date',
            'entry_time'  => 'required|date_format:H:i',
            'exit_time'   => 'nullable|date_format:H:i',
        ]);

        $tz = config('app.timezone');
        $date = $validated['date'];

        $entryTime = Carbon::parse($date . ' ' . $validated['entry_time'], $tz);

        $exitTime = null;
        $status = 'working';
        if (!empty($validated['exit_time'])) {
            $exitTime = Carbon::parse($date . ' ' . $validated['exit_time'], $tz);
            if ($exitTime->lessThanOrEqualTo($entryTime)) {
                $exitTime->addDay();
            }
            $status = 'left';
        }

        if (!$this->canAccessEmployee($request->user(), (int) $validated['employee_id'])) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        AttendanceRecord::create([
            'employee_id' => $validated['employee_id'],
            'shift_id'    => $validated['shift_id'] ?: null,
            'entry_time'  => $entryTime,
            'exit_time'   => $exitTime,
            'status'      => $status,
            'terminal_in' => 'MANUAL',
            'terminal_out' => $exitTime ? 'MANUAL' : null,
        ]);

        $month = Carbon::parse($date)->format('Y-m');

        return redirect()->route('hr.sihterica', ['month' => $month])
            ->with('success', 'Smjena uspješno unesena.');
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

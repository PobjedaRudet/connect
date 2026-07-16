<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDayStatus;
use App\Models\AttendanceRecord;
use App\Models\Shift;
use App\Services\SihtericaAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SihtericaController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    public function __construct(private readonly SihtericaAuditLogger $auditLogger)
    {
    }

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

        $user = $request->user();
        $canFilterByDepartment = $this->canFilterEmployeesByDepartment($user);

        $departmentId = null;
        if ($canFilterByDepartment) {
            $departmentId = $this->resolveAdminDepartmentFilter($request);
        }

        $employeesQuery = $this->scopedEmployeeQuery($user);

        if ($canFilterByDepartment && $departmentId !== null) {
            $employeesQuery->where('dept', $departmentId);
        }

        $employees = $employeesQuery->get(['id', 'empID', 'firstName', 'lastName', 'dept']);

        $employeeIds = $employees->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $recordsQuery = AttendanceRecord::query()
            ->with(['shift:id,name,start_time,end_time,attendance_credit_code'])
            ->whereBetween('entry_time', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->orderBy('employee_id')
            ->orderBy('entry_time');

        if (!empty($employeeIds)) {
            $recordsQuery->whereIn('employee_id', $employeeIds);
        } else {
            $recordsQuery->whereRaw('1 = 0');
        }

        $records = $recordsQuery->get([
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

            if (!isset($attendance[$employeeId][$dateKey])) {
                $attendance[$employeeId][$dateKey] = [
                    'records' => [],
                    'manual_status' => false,
                    'manual_note' => null,
                ];
            }

            $attendance[$employeeId][$dateKey]['records'][] = $this->serializeRecord($record);
        }

        foreach ($attendance as $employeeId => $byDate) {
            foreach ($byDate as $dateKey => $payload) {
                $attendance[$employeeId][$dateKey] = $this->buildDayCell($payload['records'], false, null);
            }
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

                $attendance[$employeeId][$dateKey] = $this->buildDayCell(
                    [],
                    true,
                    strtoupper((string) $manualStatus->status_code),
                    $manualStatus->note
                );
            }
        }

        $shifts = Shift::orderBy('name')->get(['id', 'name', 'start_time', 'end_time']);

        $departments = [];
        if ($canFilterByDepartment) {
            $departments = $this->departmentFilterOptions();
        }

        return Inertia::render('HR/Sihterica', [
            'month' => $monthParam,
            'days' => $days,
            'employees' => $employees->map(fn ($e) => [
                'id' => (int) $e->id,
                'empID' => (int) $e->empID,
                'full_name' => trim((string) $e->lastName . ' ' . (string) $e->firstName),
                'department_id' => $e->dept !== null ? (int) $e->dept : null,
            ])->values()->all(),
            'attendance' => $attendance,
            'shifts' => $shifts,
            'canFilterByDepartment' => $canFilterByDepartment,
            'departments' => $departments,
            'filters' => [
                'department_id' => $departmentId !== null ? (string) $departmentId : '',
            ],
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

        $record = AttendanceRecord::create([
            'employee_id' => $validated['employee_id'],
            'shift_id'    => $validated['shift_id'] ?: null,
            'entry_time'  => $entryTime,
            'effective_start' => $entryTime,
            'exit_time'   => $exitTime,
            'status'      => $status,
            'terminal_in' => 'MANUAL',
            'terminal_out' => $exitTime ? 'MANUAL' : null,
        ]);

        $this->auditLogger->logCreated($request->user(), $record, $request);

        $month = Carbon::parse($date)->format('Y-m');

        return redirect()->route('hr.sihterica', ['month' => $month])
            ->with('success', 'Smjena uspješno unesena.');
    }

    public function update(Request $request, AttendanceRecord $record)
    {
        if (!$this->canAccessEmployee($request->user(), (int) $record->employee_id)) {
            return back()->withErrors(['entry_time' => 'Nemate pristup odabranom radniku.']);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'entry_time' => 'required|date_format:H:i',
            'exit_time' => 'nullable|date_format:H:i',
            'shift_id' => 'nullable|exists:shifts,id',
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

        $before = $this->auditLogger->snapshot($record);

        $payload = [
            'entry_time' => $entryTime,
            'effective_start' => $entryTime,
            'exit_time' => $exitTime,
            'status' => $status,
            'late_flag' => null,
        ];

        if (array_key_exists('shift_id', $validated)) {
            $payload['shift_id'] = $validated['shift_id'] ?: null;
        }

        // Mark as manually corrected when HR edits punched times.
        $payload['terminal_in'] = $this->markTerminalEdited($record->terminal_in);
        $payload['terminal_out'] = $exitTime
            ? $this->markTerminalEdited($record->terminal_out)
            : null;

        $record->update($payload);

        $this->auditLogger->logUpdated($request->user(), $record->fresh(), $before, $request);

        $month = Carbon::parse($date)->format('Y-m');

        return redirect()->route('hr.sihterica', ['month' => $month])
            ->with('success', 'Vrijeme prijave/odjave uspješno ažurirano.');
    }

    public function destroy(Request $request, AttendanceRecord $record)
    {
        if (!$this->canAccessEmployee($request->user(), (int) $record->employee_id)) {
            return back()->withErrors(['record' => 'Nemate pristup odabranom radniku.']);
        }

        $month = $record->entry_time
            ? Carbon::parse($record->entry_time)->timezone(config('app.timezone'))->format('Y-m')
            : Carbon::now(config('app.timezone'))->format('Y-m');

        $before = $this->auditLogger->snapshot($record);
        $this->auditLogger->logDeleted($request->user(), $record, $before, $request);

        $record->delete();

        return redirect()->route('hr.sihterica', ['month' => $month])
            ->with('success', 'Zapis prijave/odjave obrisan.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function buildDayCell(array $records, bool $manualStatus = false, ?string $manualCode = null, ?string $manualNote = null): array
    {
        $primary = $records[0] ?? null;
        $totalMinutes = 0;
        $hasMinutes = false;
        $lateFlag = null;

        foreach ($records as $item) {
            if ($item['duration_minutes'] !== null) {
                $totalMinutes += (int) $item['duration_minutes'];
                $hasMinutes = true;
            }
            if ($lateFlag === null && !empty($item['late_flag'])) {
                $lateFlag = $item['late_flag'];
            } elseif ($item['late_flag'] === 'major') {
                $lateFlag = 'major';
            }
        }

        return [
            'record_id' => $primary['record_id'] ?? null,
            'entry_time' => $primary['entry_time'] ?? null,
            'exit_time' => $primary['exit_time'] ?? null,
            'duration_minutes' => $hasMinutes ? $totalMinutes : null,
            'duration_display' => $manualStatus
                ? $manualCode
                : (count($records) === 1 ? ($primary['duration_display'] ?? null) : null),
            'late_flag' => $lateFlag,
            'status' => $primary['status'] ?? null,
            'terminal_in' => $primary['terminal_in'] ?? null,
            'terminal_out' => $primary['terminal_out'] ?? null,
            'manual_status' => $manualStatus,
            'manual_note' => $manualNote,
            'records_count' => count($records),
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecord(AttendanceRecord $record): array
    {
        $tz = config('app.timezone');

        return [
            'record_id' => (int) $record->id,
            'shift_id' => $record->shift_id ? (int) $record->shift_id : null,
            'entry_time' => $record->entry_time?->timezone($tz)->format('H:i'),
            'exit_time' => $record->exit_time?->timezone($tz)->format('H:i'),
            'entry_date' => $record->entry_time?->timezone($tz)->toDateString(),
            'duration_minutes' => $record->duration_minutes,
            'duration_display' => $this->resolveDurationDisplay($record),
            'late_flag' => $record->late_flag,
            'status' => $record->status,
            'terminal_in' => $record->terminal_in,
            'terminal_out' => $record->terminal_out,
        ];
    }

    private function markTerminalEdited(?string $terminal): string
    {
        $terminal = trim((string) $terminal);

        if ($terminal === '' || $terminal === 'MANUAL') {
            return 'MANUAL';
        }

        if (str_starts_with($terminal, 'EDITED:')) {
            return $terminal;
        }

        return 'EDITED:' . $terminal;
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

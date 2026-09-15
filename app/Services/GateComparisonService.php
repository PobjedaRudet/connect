<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\GateLog;
use Carbon\Carbon;

/**
 * Gradi dnevni izvještaj poređenja: vrijeme ulaska/izlaska zabilježeno na kapiji
 * naspram vremena prijave/odjave zabilježenog na terminalu kod objekta.
 *
 * Svrha: otkriti da radnik ne provlači RFID karticu za drugog radnika (npr. neko
 * uđe/iziđe na kapiji, ali se prijava/odjava na objektu ne poklapa ili nedostaje).
 * Ovo je izvještaj za ručni pregled — ne blokira niti automatski šalje obavještenja.
 */
class GateComparisonService
{
    public const DEFAULT_TOLERANCE_MINUTES = 15;

    public function buildDailyReport(string $date, int $toleranceMinutes = self::DEFAULT_TOLERANCE_MINUTES): array
    {
        $tz = config('app.timezone');
        $day = Carbon::parse($date, $tz)->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $gateLogsByEmployee = GateLog::whereBetween('scanned_at', [$day, $dayEnd])
            ->orderBy('scanned_at')
            ->get()
            ->groupBy('employee_id');

        $attendanceByEmployee = AttendanceRecord::where(function ($q) use ($day, $dayEnd) {
                $q->whereBetween('entry_time', [$day, $dayEnd])
                    ->orWhereBetween('exit_time', [$day, $dayEnd]);
            })
            ->orderBy('entry_time')
            ->get()
            ->groupBy('employee_id');

        $employeeIds = $gateLogsByEmployee->keys()
            ->merge($attendanceByEmployee->keys())
            ->unique()
            ->values();

        if ($employeeIds->isEmpty()) {
            return [
                'date' => $day->toDateString(),
                'tolerance_minutes' => $toleranceMinutes,
                'rows' => [],
                'summary' => ['total' => 0, 'flagged' => 0, 'ok' => 0],
            ];
        }

        $employees = Employee::whereIn('id', $employeeIds)
            ->get(['id', 'empID', 'firstName', 'lastName'])
            ->keyBy('id');

        $rows = $employeeIds->map(function ($employeeId) use ($gateLogsByEmployee, $attendanceByEmployee, $employees, $toleranceMinutes) {
            $employee = $employees->get($employeeId);
            $gateLogs = $gateLogsByEmployee->get($employeeId, collect());
            $records = $attendanceByEmployee->get($employeeId, collect());

            $gateIn = $gateLogs->firstWhere('direction', 'in');
            $gateOut = $gateLogs->where('direction', 'out')->last();

            $buildingIn = $records->sortBy('entry_time')->first();
            $buildingOut = $records->filter(fn ($r) => $r->exit_time !== null)->sortByDesc('exit_time')->first();

            $entryDiff = ($gateIn && $buildingIn && $buildingIn->entry_time)
                ? (int) $gateIn->scanned_at->diffInMinutes($buildingIn->entry_time, false)
                : null;
            $exitDiff = ($gateOut && $buildingOut && $buildingOut->exit_time)
                ? (int) $gateOut->scanned_at->diffInMinutes($buildingOut->exit_time, false)
                : null;

            $issues = [];
            if (!$gateIn && $buildingIn) {
                $issues[] = 'Nema ulaska na kapiji';
            }
            if ($gateIn && !$buildingIn) {
                $issues[] = 'Nema prijave na objektu';
            }
            if (!$gateOut && $buildingOut) {
                $issues[] = 'Nema izlaska na kapiji';
            }
            if ($gateOut && !$buildingOut) {
                $issues[] = 'Nema odjave na objektu';
            }
            if ($entryDiff !== null && abs($entryDiff) > $toleranceMinutes) {
                $issues[] = 'Razlika ulaska > ' . $toleranceMinutes . ' min';
            }
            if ($exitDiff !== null && abs($exitDiff) > $toleranceMinutes) {
                $issues[] = 'Razlika izlaska > ' . $toleranceMinutes . ' min';
            }

            return [
                'employee_id' => (int) $employeeId,
                'empID' => $employee?->empID,
                'full_name' => trim((string) ($employee?->firstName ?? '') . ' ' . (string) ($employee?->lastName ?? '')),
                'gate_in' => $gateIn?->scanned_at?->format('H:i'),
                'gate_out' => $gateOut?->scanned_at?->format('H:i'),
                'building_in' => $buildingIn?->entry_time?->format('H:i'),
                'building_out' => $buildingOut?->exit_time?->format('H:i'),
                'entry_diff_minutes' => $entryDiff,
                'exit_diff_minutes' => $exitDiff,
                'issues' => $issues,
                'flagged' => !empty($issues),
            ];
        })
            ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'date' => $day->toDateString(),
            'tolerance_minutes' => $toleranceMinutes,
            'rows' => $rows->all(),
            'summary' => [
                'total' => $rows->count(),
                'flagged' => $rows->where('flagged', true)->count(),
                'ok' => $rows->where('flagged', false)->count(),
            ],
        ];
    }
}

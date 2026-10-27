<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\GateLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * @param  iterable<int|string>|null  $employeeIds  null = svi radnici; prazna lista = prazan izvještaj
     */
    public function buildDailyReport(
        string $date,
        int $toleranceMinutes = self::DEFAULT_TOLERANCE_MINUTES,
        ?iterable $employeeIds = null,
        ?Carbon $until = null,
    ): array {
        $tz = config('app.timezone');
        $day = Carbon::parse($date, $tz)->startOfDay();
        $dayEnd = $day->copy()->endOfDay();
        $windowEnd = $dayEnd;

        if ($until !== null) {
            $until = $until->copy()->timezone($tz);
            if ($until->lt($day)) {
                return [
                    'date' => $day->toDateString(),
                    'tolerance_minutes' => $toleranceMinutes,
                    'rows' => [],
                    'summary' => ['total' => 0, 'flagged' => 0, 'ok' => 0],
                ];
            }
            if ($until->lt($dayEnd)) {
                $windowEnd = $until;
            }
        }

        $allowedIds = $employeeIds === null
            ? null
            : collect($employeeIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($allowedIds !== null && $allowedIds->isEmpty()) {
            return [
                'date' => $day->toDateString(),
                'tolerance_minutes' => $toleranceMinutes,
                'rows' => [],
                'summary' => ['total' => 0, 'flagged' => 0, 'ok' => 0],
            ];
        }

        // Izlaz poslije ponoći pripada ulasku koji ga je otvorio, ne kalendarskom danu izlaza.
        // Djelimičan pregled (until prije kraja dana) ne gleda dalje od tog trenutka.
        $lookAheadEnd = $windowEnd->lt($dayEnd)
            ? $windowEnd->copy()
            : $day->copy()->addDay()->endOfDay();

        $gateLogsQuery = GateLog::whereBetween('scanned_at', [$day, $lookAheadEnd])
            ->orderBy('scanned_at')
            ->orderBy('id');
        if ($allowedIds !== null) {
            $gateLogsQuery->whereIn('employee_id', $allowedIds->all());
        }
        $gateLogsByEmployee = $gateLogsQuery->get()->groupBy('employee_id');

        $attendanceQuery = AttendanceRecord::whereBetween('entry_time', [$day, $windowEnd])
            ->orderBy('entry_time')
            ->orderBy('id');
        if ($allowedIds !== null) {
            $attendanceQuery->whereIn('employee_id', $allowedIds->all());
        }
        $attendanceByEmployee = $attendanceQuery->get()->groupBy('employee_id');

        $employeeIds = $attendanceByEmployee->keys()
            ->merge($gateLogsByEmployee->filter(function (Collection $logs) use ($day, $windowEnd) {
                return $logs->contains(fn (GateLog $log) => $this->isOnReportDay($log->scanned_at, $day, $windowEnd) && $log->direction === 'in');
            })->keys())
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

        $rows = $employeeIds->map(function ($employeeId) use ($gateLogsByEmployee, $attendanceByEmployee, $employees, $toleranceMinutes, $day, $windowEnd, $lookAheadEnd) {
            $employee = $employees->get($employeeId);
            $gateLogs = $gateLogsByEmployee->get($employeeId, collect());
            $records = $attendanceByEmployee->get($employeeId, collect());

            [$gateIn, $gateOut] = $this->pairGateSession($gateLogs, $day, $windowEnd, $lookAheadEnd);

            $buildingIn = $records->sortBy('entry_time')->first();
            $buildingOut = $this->exitOnSameRecord($buildingIn, $lookAheadEnd);

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
                'gate_in' => $this->formatMoment($gateIn?->scanned_at, $day),
                'gate_out' => $this->formatMoment($gateOut?->scanned_at, $day),
                'building_in' => $this->formatMoment($buildingIn?->entry_time, $day),
                'building_out' => $this->formatMoment($buildingOut?->exit_time, $day),
                'gate_in_at' => $gateIn?->scanned_at?->format('Y-m-d H:i:s'),
                'gate_out_at' => $gateOut?->scanned_at?->format('Y-m-d H:i:s'),
                'building_in_at' => $buildingIn?->entry_time?->format('Y-m-d H:i:s'),
                'building_out_at' => $buildingOut?->exit_time?->format('Y-m-d H:i:s'),
                'gate_in_id' => $gateIn?->id ? (int) $gateIn->id : null,
                'gate_out_id' => $gateOut?->id ? (int) $gateOut->id : null,
                'building_in_id' => $buildingIn?->id ? (int) $buildingIn->id : null,
                'building_out_id' => $buildingOut?->id ? (int) $buildingOut->id : null,
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

    /**
     * Ulaz je prvi ulazak izvještajnog dana. Izlaz je prvi izlazak poslije tog ulaska,
     * a prije sljedećeg ulaska. Izlazak od prethodne smjene, koji samo padne u isti kalendarski dan, se ne uzima.
     *
     * @param  Collection<int, GateLog>  $logs
     * @return array{0: ?GateLog, 1: ?GateLog}
     */
    private function pairGateSession(Collection $logs, Carbon $day, Carbon $windowEnd, Carbon $lookAheadEnd): array
    {
        $gateIn = $logs->first(function (GateLog $log) use ($day, $windowEnd) {
            return $log->direction === 'in' && $this->isOnReportDay($log->scanned_at, $day, $windowEnd);
        });

        if (!$gateIn?->scanned_at) {
            return [null, null];
        }

        $nextInAt = $logs->first(function (GateLog $log) use ($gateIn) {
            return $log->direction === 'in'
                && $log->scanned_at
                && $log->scanned_at->gt($gateIn->scanned_at);
        })?->scanned_at;

        $gateOut = $logs->first(function (GateLog $log) use ($gateIn, $nextInAt, $lookAheadEnd) {
            if ($log->direction !== 'out' || !$log->scanned_at) {
                return false;
            }
            if ($log->scanned_at->lte($gateIn->scanned_at) || $log->scanned_at->gt($lookAheadEnd)) {
                return false;
            }

            return !$nextInAt || $log->scanned_at->lt($nextInAt);
        });

        return [$gateIn, $gateOut];
    }

    private function exitOnSameRecord(?AttendanceRecord $record, Carbon $lookAheadEnd): ?AttendanceRecord
    {
        if (!$record?->entry_time || !$record->exit_time) {
            return null;
        }
        if ($record->exit_time->lt($record->entry_time) || $record->exit_time->gt($lookAheadEnd)) {
            return null;
        }

        return $record;
    }

    private function isOnReportDay(?Carbon $moment, Carbon $day, Carbon $windowEnd): bool
    {
        return $moment !== null && $moment->gte($day) && $moment->lte($windowEnd);
    }

    private function formatMoment(?Carbon $moment, Carbon $day): ?string
    {
        if (!$moment) {
            return null;
        }

        $local = $moment->copy()->timezone(config('app.timezone'));
        $time = $local->format('H:i');
        if ($local->toDateString() === $day->toDateString()) {
            return $time;
        }

        return $time . ' (' . $local->format('d.m.') . ')';
    }
}

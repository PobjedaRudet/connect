<?php

namespace App\Services;

use App\Models\AnnualLeaveDecision;
use App\Models\AnnualLeaveUsage;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnualLeaveService
{
    private const MIN_YEAR = 2000;

    /**
     * Calculates number of working days in the inclusive period [$from, $to].
     *
     * - Excludes weekends (Sat/Sun)
     * - Excludes dates present in the `holidays` table
     */
    public function calculateWorkingDays(string|\DateTimeInterface $from, string|\DateTimeInterface $to): float
    {
        $start = $this->toDate($from);
        $end = $this->toDate($to);

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $holidayDates = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip();

        $days = 0;
        for ($current = $start; $current->lessThanOrEqualTo($end); $current = $current->addDay()) {
            if ($current->isWeekend()) {
                continue;
            }

            if ($holidayDates->has($current->toDateString())) {
                continue;
            }

            $days++;
        }

        return (float) $days;
    }

    /**
     * Returns granted/used/remaining for a given employee and year.
     */
    public function getEmployeeYearBalance(int $employeeId, int $year): array
    {
        $memo = [];
        $minYear = (int) (AnnualLeaveDecision::query()
            ->where('employee_id', $employeeId)
            ->min('year') ?? $year);

        $startYear = max(self::MIN_YEAR, min($minYear, $year));

        return $this->computeEmployeeYearBalance($employeeId, $year, $startYear, $memo);
    }

    /**
     * Decisions for usage form dropdown (with remaining days + auto-carry on first decision).
     *
     * @return array{employee_id: int, year: int, decisions: list<array<string, mixed>>}
     */
    public function getDecisionsForEmployeeYear(int $employeeId, int $year): array
    {
        $manualCarrySum = (float) DB::table('annual_leave_decisions')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->sum('carried_over_days');

        $prev = $this->getEmployeeYearBalance($employeeId, $year - 1);
        $autoCarry = (int) max(0, (int) ($prev['remaining_days'] ?? 0));

        $usageAgg = DB::table('annual_leave_usages')
            ->select(
                'annual_leave_decision_id',
                DB::raw('CAST(ROUND(SUM(COALESCE(days,0)),0) AS SIGNED) as used_days')
            )
            ->groupBy('annual_leave_decision_id');

        $decisions = DB::table('annual_leave_decisions as d')
            ->leftJoinSub($usageAgg, 'u', function ($join) {
                $join->on('u.annual_leave_decision_id', '=', 'd.id');
            })
            ->where('d.employee_id', $employeeId)
            ->where('d.year', $year)
            ->orderBy('d.part')
            ->orderBy('d.id')
            ->get([
                'd.id',
                'd.part',
                'd.decision_number',
                'd.valid_from',
                'd.valid_to',
                DB::raw('CAST(ROUND(COALESCE(d.granted_days,0) + COALESCE(d.carried_over_days,0),0) AS SIGNED) as total_days'),
                DB::raw('CAST(COALESCE(u.used_days,0) AS SIGNED) as used_days'),
                DB::raw('CAST((ROUND(COALESCE(d.granted_days,0) + COALESCE(d.carried_over_days,0),0) - COALESCE(u.used_days,0)) AS SIGNED) as remaining_days'),
            ])
            ->map(fn ($d) => $this->formatDecisionOption($d))
            ->values();

        if ($decisions->isNotEmpty() && $manualCarrySum <= 0 && $autoCarry > 0) {
            $first = $decisions->first();
            $first['total_days'] = (int) $first['total_days'] + $autoCarry;
            $first['remaining_days'] = (int) $first['remaining_days'] + $autoCarry;
            $first['label'] = $this->buildDecisionLabel(
                (string) ($first['part'] ?? 'ostalo'),
                $first['decision_number'] ?? null,
                $first['valid_from'] ?? null,
                $first['valid_to'] ?? null,
                (int) $first['remaining_days']
            );

            $decisions = $decisions->values();
            $decisions->put(0, $first);
        }

        return [
            'employee_id' => $employeeId,
            'year' => $year,
            'decisions' => $decisions->values()->all(),
        ];
    }

    /**
     * Year balance details: carryover, decisions, usages.
     *
     * @return array<string, mixed>
     */
    public function getBalanceDetails(int $employeeId, int $year): array
    {
        $manualCarrySum = (float) DB::table('annual_leave_decisions')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->sum('carried_over_days');

        $prev = $this->getEmployeeYearBalance($employeeId, $year - 1);
        $autoCarry = (int) max(0, (int) ($prev['remaining_days'] ?? 0));
        $carryOverDays = $manualCarrySum > 0 ? (int) round($manualCarrySum, 0) : $autoCarry;
        $carryOverMode = $manualCarrySum > 0 ? 'stored' : 'auto';

        $decisions = DB::table('annual_leave_decisions as d')
            ->where('d.employee_id', $employeeId)
            ->where('d.year', $year)
            ->orderBy('d.part')
            ->orderBy('d.id')
            ->get([
                'd.id',
                'd.part',
                'd.decision_number',
                'd.decision_date',
                'd.valid_from',
                'd.valid_to',
                DB::raw('CAST(ROUND(COALESCE(d.granted_days,0) + COALESCE(d.carried_over_days,0),0) AS SIGNED) as total_days'),
            ])
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'part' => (string) ($d->part ?? 'ostalo'),
                'decision_number' => $d->decision_number,
                'decision_date' => $d->decision_date,
                'valid_from' => $d->valid_from,
                'valid_to' => $d->valid_to,
                'total_days' => (int) $d->total_days,
            ])
            ->values()
            ->all();

        $usages = DB::table('annual_leave_usages as u')
            ->join('annual_leave_decisions as d', 'd.id', '=', 'u.annual_leave_decision_id')
            ->where('d.employee_id', $employeeId)
            ->where('d.year', $year)
            ->orderBy('u.date_from')
            ->orderBy('u.id')
            ->get([
                'u.id',
                'u.annual_leave_decision_id',
                'u.date_from',
                'u.date_to',
                DB::raw('CAST(ROUND(COALESCE(u.days,0),0) AS SIGNED) as days'),
                'u.note',
                'd.part',
                'd.decision_number',
            ])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'annual_leave_decision_id' => (int) $u->annual_leave_decision_id,
                'date_from' => $u->date_from,
                'date_to' => $u->date_to,
                'days' => (int) $u->days,
                'note' => $u->note,
                'part' => (string) ($u->part ?? 'ostalo'),
                'decision_number' => $u->decision_number,
            ])
            ->values()
            ->all();

        return [
            'employee_id' => $employeeId,
            'year' => $year,
            'carryover_days' => $carryOverDays,
            'carryover_mode' => $carryOverMode,
            'carryover_from_year' => $carryOverMode === 'auto' ? ($year - 1) : null,
            'decisions' => $decisions,
            'usages' => $usages,
        ];
    }

    /**
     * Per-employee year balances (with carry-over chain) for a fixed set of employees.
     *
     * @param  Collection<int, object>|iterable<int, object>  $employees  objects with employee_id, firstName, lastName
     * @return array{year: int, rows: list<array<string, mixed>>}
     */
    public function getBalanceAll(int $year, iterable $employees): array
    {
        $minYear = (int) (DB::table('annual_leave_decisions')->min('year') ?? $year);
        $minYear = max(self::MIN_YEAR, min($minYear, $year));

        $decisionsAgg = DB::table('annual_leave_decisions')
            ->whereBetween('year', [$minYear, $year])
            ->select([
                'employee_id',
                'year',
                DB::raw('CAST(ROUND(SUM(COALESCE(granted_days,0)), 0) AS SIGNED) as granted_days'),
                DB::raw('CAST(ROUND(SUM(COALESCE(carried_over_days,0)), 0) AS SIGNED) as carried_over_days'),
            ])
            ->groupBy('employee_id', 'year')
            ->get();

        $usageAgg = DB::table('annual_leave_usages as u')
            ->join('annual_leave_decisions as d', 'd.id', '=', 'u.annual_leave_decision_id')
            ->whereBetween('d.year', [$minYear, $year])
            ->select([
                'd.employee_id',
                'd.year',
                DB::raw('CAST(ROUND(SUM(COALESCE(u.days,0)), 0) AS SIGNED) as used_days'),
            ])
            ->groupBy('d.employee_id', 'd.year')
            ->get();

        $granted = [];
        $manualCarry = [];
        foreach ($decisionsAgg as $r) {
            $eid = (int) $r->employee_id;
            $y = (int) $r->year;
            $granted[$eid][$y] = (int) $r->granted_days;
            $manualCarry[$eid][$y] = (int) $r->carried_over_days;
        }

        $used = [];
        foreach ($usageAgg as $r) {
            $eid = (int) $r->employee_id;
            $y = (int) $r->year;
            $used[$eid][$y] = (int) $r->used_days;
        }

        $rows = Collection::make($employees)->map(function ($e) use ($year, $minYear, $granted, $manualCarry, $used) {
            $eid = (int) $e->employee_id;
            $prevRemaining = 0;
            $totalDays = 0;
            $usedDays = 0;
            $remainingDays = 0;

            for ($y = $minYear; $y <= $year; $y++) {
                $g = (int) ($granted[$eid][$y] ?? 0);
                $mCarry = (int) ($manualCarry[$eid][$y] ?? 0);
                $u = (int) ($used[$eid][$y] ?? 0);

                $autoCarry = max(0, (int) $prevRemaining);
                $carry = $mCarry > 0 ? $mCarry : $autoCarry;
                $approved = $g + $carry;
                $remaining = $approved - $u;

                if ($y === $year) {
                    $totalDays = (int) $approved;
                    $usedDays = (int) $u;
                    $remainingDays = (int) $remaining;
                }

                $prevRemaining = $remaining;
            }

            return [
                'employee_id' => $eid,
                'firstName' => $e->firstName,
                'lastName' => $e->lastName,
                'year' => $year,
                'used_days' => $usedDays,
                'total_days' => $totalDays,
                'remaining_days' => $remainingDays,
            ];
        })->values()->all();

        return [
            'year' => $year,
            'rows' => $rows,
        ];
    }

    /**
     * Lifetime granted/used/remaining (no carry-over) for a fixed set of employees.
     *
     * @param  Collection<int, object>|iterable<int, object>  $employees
     * @return array{rows: list<array<string, mixed>>}
     */
    public function getBalanceSummary(iterable $employees): array
    {
        $decisionsAgg = DB::table('annual_leave_decisions')
            ->select([
                'employee_id',
                DB::raw('CAST(ROUND(SUM(COALESCE(granted_days,0)), 0) AS SIGNED) as total_granted'),
            ])
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $usageAgg = DB::table('annual_leave_usages as u')
            ->join('annual_leave_decisions as d', 'd.id', '=', 'u.annual_leave_decision_id')
            ->select([
                'd.employee_id',
                DB::raw('CAST(ROUND(SUM(COALESCE(u.days,0)), 0) AS SIGNED) as total_used'),
            ])
            ->groupBy('d.employee_id')
            ->get()
            ->keyBy('employee_id');

        $rows = Collection::make($employees)->map(function ($e) use ($decisionsAgg, $usageAgg) {
            $eid = (int) $e->employee_id;
            $granted = (int) ($decisionsAgg[$eid]->total_granted ?? 0);
            $used = (int) ($usageAgg[$eid]->total_used ?? 0);

            return [
                'employee_id' => $eid,
                'firstName' => $e->firstName,
                'lastName' => $e->lastName,
                'total_days' => $granted,
                'used_days' => $used,
                'remaining_days' => $granted - $used,
            ];
        })->values()->all();

        return ['rows' => $rows];
    }

    /**
     * Lifetime summary details for one employee (year totals + all usages).
     *
     * @return array{employee_id: int, year_totals: list<array<string, mixed>>, usages: list<array<string, mixed>>}
     */
    public function getBalanceSummaryDetails(int $employeeId): array
    {
        $yearTotals = DB::table('annual_leave_decisions')
            ->where('employee_id', $employeeId)
            ->select([
                'year',
                DB::raw('CAST(ROUND(SUM(COALESCE(granted_days,0)), 0) AS SIGNED) as granted_days'),
            ])
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(fn ($r) => [
                'year' => (int) $r->year,
                'granted_days' => (int) $r->granted_days,
            ])
            ->values()
            ->all();

        $usages = DB::table('annual_leave_usages as u')
            ->join('annual_leave_decisions as d', 'd.id', '=', 'u.annual_leave_decision_id')
            ->where('d.employee_id', $employeeId)
            ->orderBy('u.date_from')
            ->orderBy('u.id')
            ->get([
                'u.id',
                'u.date_from',
                'u.date_to',
                DB::raw('CAST(ROUND(COALESCE(u.days,0),0) AS SIGNED) as days'),
                'u.note',
                'd.year',
                'd.part',
                'd.decision_number',
            ])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'date_from' => $u->date_from,
                'date_to' => $u->date_to,
                'days' => (int) $u->days,
                'note' => $u->note,
                'year' => (int) $u->year,
                'part' => (string) ($u->part ?? 'ostalo'),
                'decision_number' => $u->decision_number,
            ])
            ->values()
            ->all();

        return [
            'employee_id' => $employeeId,
            'year_totals' => $yearTotals,
            'usages' => $usages,
        ];
    }

    /**
     * Returns granted/used/remaining for a given decision.
     */
    public function getDecisionBalance(AnnualLeaveDecision $decision): array
    {
        $grantedRaw = (float) $decision->granted_days + (float) $decision->carried_over_days;

        $usedRaw = (float) AnnualLeaveUsage::query()
            ->where('annual_leave_decision_id', $decision->id)
            ->sum('days');

        $granted = $this->asWholeDays($grantedRaw);
        $used = $this->asWholeDays($usedRaw);

        return [
            'decision_id' => $decision->id,
            'employee_id' => $decision->employee_id,
            'year' => (int) $decision->year,
            'granted_days' => $granted,
            'used_days' => $used,
            'remaining_days' => $granted - $used,
        ];
    }

    /**
     * Convenience: calculates working days for a usage row (date_from/date_to).
     */
    public function calculateUsageDays(AnnualLeaveUsage $usage): float
    {
        return $this->calculateWorkingDays($usage->date_from, $usage->date_to);
    }

    private function computeEmployeeYearBalance(int $employeeId, int $year, int $startYear, array &$memo): array
    {
        $year = (int) $year;

        if ($year < $startYear) {
            return [
                'decision_id' => null,
                'decision_ids' => [],
                'employee_id' => $employeeId,
                'year' => $year,
                'granted_days' => 0,
                'used_days' => 0,
                'remaining_days' => 0,
            ];
        }

        $key = $employeeId . ':' . $year;
        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }

        $decisions = AnnualLeaveDecision::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get();

        $prev = $this->computeEmployeeYearBalance($employeeId, $year - 1, $startYear, $memo);
        $autoCarry = (int) max(0, (int) ($prev['remaining_days'] ?? 0));

        if ($decisions->isEmpty()) {
            $result = [
                'decision_id' => null,
                'decision_ids' => [],
                'employee_id' => $employeeId,
                'year' => $year,
                'granted_days' => $autoCarry,
                'used_days' => 0,
                'remaining_days' => $autoCarry,
            ];

            return $memo[$key] = $result;
        }

        $grantedSum = (float) $decisions->sum(fn (AnnualLeaveDecision $d) => (float) $d->granted_days);
        $manualCarrySum = (float) $decisions->sum(fn (AnnualLeaveDecision $d) => (float) $d->carried_over_days);
        $carry = $manualCarrySum > 0 ? $manualCarrySum : $autoCarry;

        $usedRaw = (float) AnnualLeaveUsage::query()
            ->join('annual_leave_decisions as d', 'd.id', '=', 'annual_leave_usages.annual_leave_decision_id')
            ->where('d.employee_id', $employeeId)
            ->where('d.year', $year)
            ->sum('annual_leave_usages.days');

        $decisionIds = $decisions->pluck('id')->values();

        $granted = $this->asWholeDays($grantedSum + $carry);
        $used = $this->asWholeDays($usedRaw);

        $result = [
            'decision_id' => $decisionIds->first(),
            'decision_ids' => $decisionIds,
            'employee_id' => $employeeId,
            'year' => $year,
            'granted_days' => $granted,
            'used_days' => $used,
            'remaining_days' => $granted - $used,
        ];

        return $memo[$key] = $result;
    }

    /**
     * @param  object{
     *     id: mixed,
     *     part: mixed,
     *     decision_number: mixed,
     *     valid_from: mixed,
     *     valid_to: mixed,
     *     total_days: mixed,
     *     used_days: mixed,
     *     remaining_days: mixed
     * }  $d
     * @return array<string, mixed>
     */
    private function formatDecisionOption(object $d): array
    {
        $part = $d->part ?? 'ostalo';
        $remaining = (int) $d->remaining_days;

        return [
            'id' => (int) $d->id,
            'part' => (string) $part,
            'decision_number' => $d->decision_number,
            'valid_from' => $d->valid_from,
            'valid_to' => $d->valid_to,
            'total_days' => (int) $d->total_days,
            'used_days' => (int) $d->used_days,
            'remaining_days' => $remaining,
            'label' => $this->buildDecisionLabel(
                (string) $part,
                $d->decision_number,
                $d->valid_from,
                $d->valid_to,
                $remaining
            ),
        ];
    }

    private function buildDecisionLabel(
        string $part,
        mixed $decisionNumber,
        mixed $validFrom,
        mixed $validTo,
        int $remainingDays
    ): string {
        $num = $decisionNumber ? (' #' . $decisionNumber) : '';
        $range = ($validFrom || $validTo)
            ? (' (' . ($validFrom ?? '?') . ' - ' . ($validTo ?? '?') . ')')
            : '';

        return strtoupper($part) . $num . $range . ' | Preostalo: ' . $remainingDays;
    }

    private function asWholeDays(float|int $value): int
    {
        return (int) round((float) $value, 0);
    }

    private function toDate(string|\DateTimeInterface $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }
}

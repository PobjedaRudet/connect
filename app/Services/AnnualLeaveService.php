<?php

namespace App\Services;

use App\Models\AnnualLeaveDecision;
use App\Models\AnnualLeaveUsage;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class AnnualLeaveService
{
    private const MIN_YEAR = 2000;
    /**
     * Calculates number of working days in the inclusive period [$from, $to].
     *
     * - Excludes weekends (Sat/Sun)
     * - Excludes dates present in the `holidays` table
    *
    * Example:
    *   $days = app(AnnualLeaveService::class)->calculateWorkingDays('2026-05-01', '2026-05-10');
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

    private function asWholeDays(float|int $value): int
    {
        return (int) round((float) $value, 0);
    }

    /**
     * Returns granted/used/remaining for a given employee and year.
        *
        * Example:
        *   $balance = app(AnnualLeaveService::class)->getEmployeeYearBalance($employeeId, 2026);
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

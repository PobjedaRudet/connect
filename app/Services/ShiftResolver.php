<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;

class ShiftResolver
{
    /**
     * Resolve nearest shift for an employee. If employee has explicit shifts via pivot, use them;
     * otherwise fall back to department shifts.
     */
    public function resolveNearest(Employee $employee, Carbon $checkTime): ?Shift
    {
        $assigned = $employee->shifts()->exists()
            ? $employee->shifts()->get()
            : ($employee->department ? $employee->department->shifts : collect());

        if ($assigned->isEmpty()) {
            return null;
        }

        $date = $checkTime->copy()->startOfDay();
        $best = null;
        $bestDiff = null;

        foreach ($assigned as $shift) {
            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->start_time);
            $diff = abs($checkTime->diffInMinutes($start));
            if ($best === null || $diff < $bestDiff) {
                $best = $shift;
                $bestDiff = $diff;
            }
        }
        return $best;
    }

    /**
     * Round late start according to 15-min blocks:
     * examples: 7:31→7:45, 7:49→8:00.
     * If check-in before shift start, returns shift start.
     */
    public function roundEffectiveStart(Carbon $checkTime, Carbon $shiftStart): Carbon
    {
        if ($checkTime->lte($shiftStart)) {
            return $shiftStart->copy();
        }

        $minutesLate = $shiftStart->diffInMinutes($checkTime);

        // Round up to next 15-min block from shift start.
        $blocks = (int) ceil($minutesLate / 15);
        return $shiftStart->copy()->addMinutes($blocks * 15);
    }
}

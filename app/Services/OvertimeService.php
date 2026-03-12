<?php

namespace App\Services;

use App\Models\AttendanceOvertime;
use App\Models\OvertimeUsage;
use App\Models\OvertimeUsageAllocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeService
{
    public const USAGE_TYPES = [
        'slobodni_sati',
        'raniji_izlazak',
        'kasniji_dolazak',
        'slobodan_dan',
        'manualno',
    ];

    public function formatMinutes(int $minutes): string
    {
        $hours = intdiv(max($minutes, 0), 60);
        $remainingMinutes = max($minutes, 0) % 60;

        return sprintf('%d:%02d', $hours, $remainingMinutes);
    }

    public function getAvailableSlots(int $employeeId, string $usageDate): Collection
    {
        $usageDate = Carbon::parse($usageDate)->toDateString();

        $allocationAgg = OvertimeUsageAllocation::query()
            ->select('attendance_overtime_id', DB::raw('COALESCE(SUM(minutes_allocated), 0) as allocated_minutes'))
            ->groupBy('attendance_overtime_id');

        return AttendanceOvertime::query()
            ->leftJoinSub($allocationAgg, 'alloc', function ($join) {
                $join->on('alloc.attendance_overtime_id', '=', 'attendance_overtimes.id');
            })
            ->where('attendance_overtimes.employee_id', $employeeId)
            ->whereDate('attendance_overtimes.work_date', '<=', $usageDate)
            ->orderBy('attendance_overtimes.work_date')
            ->orderBy('attendance_overtimes.id')
            ->get([
                'attendance_overtimes.id',
                'attendance_overtimes.work_date',
                'attendance_overtimes.overtime_minutes',
                DB::raw('COALESCE(alloc.allocated_minutes, 0) as allocated_minutes'),
            ])
            ->map(function ($row) {
                $earnedMinutes = (int) $row->overtime_minutes;
                $usedMinutes = (int) $row->allocated_minutes;
                $remainingMinutes = max($earnedMinutes - $usedMinutes, 0);

                return [
                    'attendance_overtime_id' => (int) $row->id,
                    'work_date' => Carbon::parse($row->work_date)->toDateString(),
                    'earned_minutes' => $earnedMinutes,
                    'used_minutes' => $usedMinutes,
                    'remaining_minutes' => $remainingMinutes,
                    'earned_display' => $this->formatMinutes($earnedMinutes),
                    'used_display' => $this->formatMinutes($usedMinutes),
                    'remaining_display' => $this->formatMinutes($remainingMinutes),
                ];
            })
            ->filter(fn (array $slot) => $slot['remaining_minutes'] > 0)
            ->values();
    }

    public function getAvailableBalance(int $employeeId, string $usageDate, ?int $minutesRequested = null): array
    {
        $slots = $this->getAvailableSlots($employeeId, $usageDate);
        $availableMinutes = (int) $slots->sum('remaining_minutes');

        return [
            'employee_id' => $employeeId,
            'usage_date' => Carbon::parse($usageDate)->toDateString(),
            'available_minutes' => $availableMinutes,
            'available_display' => $this->formatMinutes($availableMinutes),
            'slots' => $slots->all(),
            'preview' => $minutesRequested && $minutesRequested > 0
                ? $this->buildPreviewFromSlots($slots, $minutesRequested)
                : null,
        ];
    }

    public function createUsage(
        int $employeeId,
        string $usageDate,
        int $minutesUsed,
        string $usageType,
        ?string $note = null,
        ?int $createdByUserId = null
    ): OvertimeUsage {
        $preview = $this->buildPreview($employeeId, $usageDate, $minutesUsed);

        if (!$preview['is_possible']) {
            throw ValidationException::withMessages([
                'minutes_used' => 'Radnik nema dovoljno raspoloživih prekovremenih sati. Nedostaje: ' . $preview['shortage_display'] . '.',
            ]);
        }

        return DB::transaction(function () use ($employeeId, $usageDate, $minutesUsed, $usageType, $note, $createdByUserId, $preview) {
            $usage = OvertimeUsage::create([
                'employee_id' => $employeeId,
                'usage_date' => Carbon::parse($usageDate)->toDateString(),
                'minutes_used' => $minutesUsed,
                'usage_type' => $usageType,
                'note' => $note,
                'created_by_user_id' => $createdByUserId,
            ]);

            foreach ($preview['allocations'] as $allocation) {
                OvertimeUsageAllocation::create([
                    'overtime_usage_id' => $usage->id,
                    'attendance_overtime_id' => $allocation['attendance_overtime_id'],
                    'minutes_allocated' => $allocation['allocated_minutes'],
                ]);
            }

            return $usage->load(['allocations.overtime']);
        });
    }

    public function buildPreview(int $employeeId, string $usageDate, int $minutesRequested): array
    {
        return $this->buildPreviewFromSlots($this->getAvailableSlots($employeeId, $usageDate), $minutesRequested);
    }

    public function getMonthlyOverview(Collection $employeeIds, Carbon $monthStart, Carbon $monthEnd): array
    {
        $records = AttendanceOvertime::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->get([
                'id',
                'employee_id',
                'work_date',
                'overtime_minutes',
            ]);

        $recordIds = $records->pluck('id');

        $allocations = OvertimeUsageAllocation::query()
            ->with(['usage:id,usage_date,usage_type,note'])
            ->whereIn('attendance_overtime_id', $recordIds)
            ->orderBy('id')
            ->get([
                'id',
                'overtime_usage_id',
                'attendance_overtime_id',
                'minutes_allocated',
            ])
            ->groupBy('attendance_overtime_id');

        $overtime = [];
        $totals = [];

        foreach ($records as $record) {
            $employeeId = (int) $record->employee_id;
            $dateKey = $record->work_date?->toDateString() ?? (string) $record->work_date;
            $earnedMinutes = (int) $record->overtime_minutes;

            if (!isset($overtime[$employeeId][$dateKey])) {
                $overtime[$employeeId][$dateKey] = [
                    'earned_minutes' => 0,
                    'used_minutes' => 0,
                    'remaining_minutes' => 0,
                    'earned_display' => '0:00',
                    'used_display' => '0:00',
                    'remaining_display' => '0:00',
                    'status' => 'unused',
                    'usages' => [],
                ];
            }

            if (!isset($totals[$employeeId])) {
                $totals[$employeeId] = [
                    'earned_minutes' => 0,
                    'used_minutes' => 0,
                    'remaining_minutes' => 0,
                    'earned_display' => '0:00',
                    'used_display' => '0:00',
                    'remaining_display' => '0:00',
                ];
            }

            $recordAllocations = $allocations->get($record->id, collect());
            $usedMinutes = (int) $recordAllocations->sum('minutes_allocated');
            $remainingMinutes = max($earnedMinutes - $usedMinutes, 0);

            $overtime[$employeeId][$dateKey]['earned_minutes'] += $earnedMinutes;
            $overtime[$employeeId][$dateKey]['used_minutes'] += $usedMinutes;
            $overtime[$employeeId][$dateKey]['remaining_minutes'] += $remainingMinutes;

            foreach ($recordAllocations as $allocation) {
                $usage = $allocation->usage;

                $overtime[$employeeId][$dateKey]['usages'][] = [
                    'id' => (int) $allocation->id,
                    'usage_id' => (int) $allocation->overtime_usage_id,
                    'usage_date' => $usage?->usage_date?->toDateString(),
                    'usage_type' => (string) ($usage?->usage_type ?? 'manualno'),
                    'note' => $usage?->note,
                    'allocated_minutes' => (int) $allocation->minutes_allocated,
                    'allocated_display' => $this->formatMinutes((int) $allocation->minutes_allocated),
                ];
            }

            $totals[$employeeId]['earned_minutes'] += $earnedMinutes;
            $totals[$employeeId]['used_minutes'] += $usedMinutes;
            $totals[$employeeId]['remaining_minutes'] += $remainingMinutes;
        }

        foreach ($overtime as $employeeId => $days) {
            foreach ($days as $dateKey => $entry) {
                $overtime[$employeeId][$dateKey]['earned_display'] = $this->formatMinutes((int) $entry['earned_minutes']);
                $overtime[$employeeId][$dateKey]['used_display'] = $this->formatMinutes((int) $entry['used_minutes']);
                $overtime[$employeeId][$dateKey]['remaining_display'] = $this->formatMinutes((int) $entry['remaining_minutes']);
                $overtime[$employeeId][$dateKey]['status'] = $entry['used_minutes'] <= 0
                    ? 'unused'
                    : ($entry['remaining_minutes'] > 0 ? 'partial' : 'used');
            }
        }

        foreach ($totals as $employeeId => $entry) {
            $totals[$employeeId]['earned_display'] = $this->formatMinutes((int) $entry['earned_minutes']);
            $totals[$employeeId]['used_display'] = $this->formatMinutes((int) $entry['used_minutes']);
            $totals[$employeeId]['remaining_display'] = $this->formatMinutes((int) $entry['remaining_minutes']);
        }

        return [
            'overtime' => $overtime,
            'totals' => $totals,
        ];
    }

    private function buildPreviewFromSlots(Collection $slots, int $minutesRequested): array
    {
        $availableMinutes = (int) $slots->sum('remaining_minutes');
        $remainingToAllocate = max($minutesRequested, 0);
        $allocations = [];

        foreach ($slots as $slot) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            $allocated = min((int) $slot['remaining_minutes'], $remainingToAllocate);
            if ($allocated <= 0) {
                continue;
            }

            $allocations[] = [
                'attendance_overtime_id' => (int) $slot['attendance_overtime_id'],
                'work_date' => $slot['work_date'],
                'allocated_minutes' => $allocated,
                'allocated_display' => $this->formatMinutes($allocated),
            ];

            $remainingToAllocate -= $allocated;
        }

        return [
            'requested_minutes' => $minutesRequested,
            'requested_display' => $this->formatMinutes($minutesRequested),
            'available_minutes' => $availableMinutes,
            'available_display' => $this->formatMinutes($availableMinutes),
            'is_possible' => $remainingToAllocate === 0,
            'shortage_minutes' => $remainingToAllocate,
            'shortage_display' => $this->formatMinutes($remainingToAllocate),
            'allocations' => $allocations,
        ];
    }
}

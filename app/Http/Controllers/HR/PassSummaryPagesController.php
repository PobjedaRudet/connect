<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Pass;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PassSummaryPagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;
    public function index(Request $request): Response
    {
        $monthParam = trim((string) $request->query('month', ''));

        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = Carbon::now(config('app.timezone'))->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $monthParam, config('app.timezone'))->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $employees = $this->scopedEmployeeQuery($request->user())
            ->get(['id', 'empID', 'firstName', 'lastName'])
            ->keyBy('id');

        $employeeIds = $employees->keys();

        $summary = Pass::query()
            ->where('approved', true)
            ->where('status', 'closed')
            ->whereNotNull('duration_minutes')
            ->whereBetween('start_time', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->when($employeeIds->isNotEmpty(), function ($query) use ($employeeIds) {
                $query->whereIn('employee_id', $employeeIds->all());
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->selectRaw('employee_id')
            ->selectRaw("SUM(CASE WHEN type = 'privatni' THEN COALESCE(duration_minutes, 0) ELSE 0 END) as private_minutes")
            ->selectRaw("SUM(CASE WHEN type = 'službeni' THEN COALESCE(duration_minutes, 0) ELSE 0 END) as business_minutes")
            ->selectRaw('SUM(COALESCE(duration_minutes, 0)) as total_minutes')
            ->selectRaw("SUM(CASE WHEN type = 'privatni' THEN 1 ELSE 0 END) as private_count")
            ->selectRaw("SUM(CASE WHEN type = 'službeni' THEN 1 ELSE 0 END) as business_count")
            ->groupBy('employee_id')
            ->get()
            ->map(function ($row) use ($employees) {
                $employee = $employees->get((int) $row->employee_id);

                return [
                    'employee_id' => (int) $row->employee_id,
                    'empID' => (int) ($employee?->empID ?? 0),
                    'full_name' => trim((string) ($employee?->lastName ?? '') . ' ' . (string) ($employee?->firstName ?? '')),
                    'private_minutes' => (int) $row->private_minutes,
                    'business_minutes' => (int) $row->business_minutes,
                    'total_minutes' => (int) $row->total_minutes,
                    'private_count' => (int) $row->private_count,
                    'business_count' => (int) $row->business_count,
                    'private_display' => $this->formatMinutes((int) $row->private_minutes),
                    'business_display' => $this->formatMinutes((int) $row->business_minutes),
                    'total_display' => $this->formatMinutes((int) $row->total_minutes),
                ];
            })
            ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $totals = [
            'private_minutes' => (int) $summary->sum('private_minutes'),
            'business_minutes' => (int) $summary->sum('business_minutes'),
            'total_minutes' => (int) $summary->sum('total_minutes'),
            'private_count' => (int) $summary->sum('private_count'),
            'business_count' => (int) $summary->sum('business_count'),
            'employees_count' => (int) $summary->count(),
        ];

        $totals['private_display'] = $this->formatMinutes($totals['private_minutes']);
        $totals['business_display'] = $this->formatMinutes($totals['business_minutes']);
        $totals['total_display'] = $this->formatMinutes($totals['total_minutes']);

        $availableMonths = collect(range(0, 11))
            ->map(fn (int $offset) => Carbon::now(config('app.timezone'))->startOfMonth()->subMonths($offset)->format('Y-m'));

        $selectedEmployeeId = (int) $request->query('employee_id', 0);
        $employeePasses = [];
        $selectedEmployee = null;

        if ($selectedEmployeeId > 0 && $employees->has($selectedEmployeeId)) {
            $employee = $employees->get($selectedEmployeeId);
            $selectedEmployee = [
                'employee_id' => $selectedEmployeeId,
                'empID' => (int) ($employee->empID ?? 0),
                'full_name' => trim((string) ($employee->lastName ?? '') . ' ' . (string) ($employee->firstName ?? '')),
            ];

            $tz = config('app.timezone');

            $select = [
                'id',
                'type',
                'reason',
                'start_time',
                'end_time',
                'duration_minutes',
            ];
            foreach (['late_pass', 'late_minutes', 'early_departure', 'early_minutes'] as $optionalCol) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('passes', $optionalCol)) {
                    $select[] = $optionalCol;
                }
            }

            $employeePasses = Pass::query()
                ->where('employee_id', $selectedEmployeeId)
                ->where('approved', true)
                ->where('status', 'closed')
                ->whereNotNull('duration_minutes')
                ->whereBetween('start_time', [
                    $monthStart->copy()->startOfDay(),
                    $monthEnd->copy()->endOfDay(),
                ])
                ->orderByDesc('start_time')
                ->get($select)
                ->map(function (Pass $pass) use ($tz) {
                    return [
                        'id' => $pass->id,
                        'type' => $pass->type,
                        'reason' => $pass->reason,
                        'start_time' => optional($pass->start_time)->timezone($tz)->format('Y-m-d H:i:s'),
                        'end_time' => optional($pass->end_time)->timezone($tz)->format('Y-m-d H:i:s'),
                        'duration_minutes' => (int) ($pass->duration_minutes ?? 0),
                        'duration_display' => $this->formatMinutes((int) ($pass->duration_minutes ?? 0)),
                        'late_pass' => (bool) ($pass->late_pass ?? false),
                        'late_minutes' => isset($pass->late_minutes) && $pass->late_minutes !== null ? (int) $pass->late_minutes : null,
                        'early_departure' => (bool) ($pass->early_departure ?? false),
                        'early_minutes' => isset($pass->early_minutes) && $pass->early_minutes !== null ? (int) $pass->early_minutes : null,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('HR/IzlazniceSumarnoPoMjesecu', [
            'selectedMonth' => $monthParam,
            'availableMonths' => $availableMonths,
            'summary' => $summary,
            'totals' => $totals,
            'selectedEmployee' => $selectedEmployee,
            'employeePasses' => $employeePasses,
        ]);
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv(max($minutes, 0), 60);
        $remainingMinutes = max($minutes, 0) % 60;

        return sprintf('%d:%02d', $hours, $remainingMinutes);
    }
}

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
    public function index(Request $request): Response
    {
        $monthParam = trim((string) $request->query('month', ''));

        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = Carbon::now(config('app.timezone'))->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $monthParam, config('app.timezone'))->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $userId = $request->user()?->id;

        $employees = Employee::query()
            ->where('Active', true)
            ->when($userId, function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->whereJsonContains('nadlezne_osobe', (int) $userId)
                        ->orWhereJsonContains('nadlezne_osobe', (string) $userId);
                });
            })
            ->orderBy('lastName')
            ->orderBy('firstName')
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

        return Inertia::render('HR/IzlazniceSumarnoPoMjesecu', [
            'selectedMonth' => $monthParam,
            'availableMonths' => $availableMonths,
            'summary' => $summary,
            'totals' => $totals,
        ]);
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv(max($minutes, 0), 60);
        $remainingMinutes = max($minutes, 0) % 60;

        return sprintf('%d:%02d', $hours, $remainingMinutes);
    }
}

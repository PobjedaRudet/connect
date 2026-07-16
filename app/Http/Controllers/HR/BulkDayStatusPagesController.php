<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDayStatus;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BulkDayStatusPagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    public function index(): Response
    {
        $recentRows = collect();

        if (Schema::hasTable('attendance_day_statuses')) {
            $recentRows = AttendanceDayStatus::query()
                ->with(['employee:id,firstName,lastName'])
                ->orderByDesc('work_date')
                ->orderByDesc('id')
                ->limit(120)
                ->get()
                ->map(function (AttendanceDayStatus $row): array {
                    $employeeName = $row->employee
                        ? trim((string) $row->employee->lastName . ' ' . (string) $row->employee->firstName)
                        : null;

                    return [
                        'id' => (int) $row->id,
                        'employee_id' => (int) $row->employee_id,
                        'employee_name' => $employeeName,
                        'work_date' => $row->work_date?->toDateString(),
                        'status_code' => (string) $row->status_code,
                        'note' => $row->note,
                        'created_at' => $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    ];
                })
                ->values();
        }

        return Inertia::render('HR/MasovnaDodjelaStatusa', [
            'recentRows' => $recentRows,
            'departments' => $this->departmentFilterOptions(),
            'allowedStatuses' => [
                ['code' => 'P', 'label' => 'P - Praznik'],
                ['code' => 'GO', 'label' => 'GO - Godisnji odmor'],
                ['code' => 'BO', 'label' => 'BO - Bolovanje'],
                ['code' => 'PO', 'label' => 'PO - Placeno odsustvo'],
                ['code' => 'RP', 'label' => 'RP - Rad na praznik'],
                ['code' => 'PR', 'label' => 'PR - Prekovremeni rad'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (!Schema::hasTable('attendance_day_statuses')) {
            return back()->withErrors([
                'status_code' => 'Tabela attendance_day_statuses ne postoji. Pokrenite migracije (php artisan migrate).',
            ]);
        }

        foreach (['note'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'status_code' => ['required', 'in:P,GO,BO,PO,RP,PR'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'note' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'in:all,departments'],
            'department_ids' => [
                Rule::requiredIf(fn () => $request->input('scope') === 'departments'),
                'nullable',
                'array',
            ],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->startOfDay();

        $totalDays = $from->diffInDays($to) + 1;
        if ($totalDays > 366) {
            return back()->withErrors([
                'to' => 'Maksimalni dozvoljeni raspon je 366 dana.',
            ]);
        }

        $departmentIds = collect($validated['department_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $employeesQuery = $this->scopedEmployeeQuery($request->user());

        if (($validated['scope'] ?? 'all') === 'departments') {
            if (empty($departmentIds)) {
                return back()->withErrors([
                    'department_ids' => 'Odaberite najmanje jedan odjel.',
                ]);
            }

            $employeesQuery->whereIn('dept', $departmentIds);
        }

        $employeeIds = $employeesQuery
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($employeeIds)) {
            return back()->withErrors([
                'status_code' => ($validated['scope'] ?? 'all') === 'departments'
                    ? 'Nema aktivnih radnika u odabranim odjelima.'
                    : 'Nema aktivnih radnika za dodjelu statusa.',
            ]);
        }

        $period = CarbonPeriod::create($from->toDateString(), $to->toDateString());
        $now = now();
        $statusCode = (string) $validated['status_code'];
        $note = $validated['note'] ?? null;
        $createdByUserId = $request->user()?->id;

        $batch = [];
        $affectedRows = 0;

        foreach ($period as $date) {
            $dateStr = $date->toDateString();

            foreach ($employeeIds as $employeeId) {
                $batch[] = [
                    'employee_id' => $employeeId,
                    'work_date' => $dateStr,
                    'status_code' => $statusCode,
                    'note' => $note,
                    'created_by_user_id' => $createdByUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 1000) {
                    AttendanceDayStatus::upsert(
                        $batch,
                        ['employee_id', 'work_date'],
                        ['status_code', 'note', 'created_by_user_id', 'updated_at']
                    );

                    $affectedRows += count($batch);
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            AttendanceDayStatus::upsert(
                $batch,
                ['employee_id', 'work_date'],
                ['status_code', 'note', 'created_by_user_id', 'updated_at']
            );

            $affectedRows += count($batch);
        }

        return redirect()
            ->route('hr.statusi.masovno')
            ->banner('Status "' . $statusCode . '" dodijeljen/azuriran za ' . $affectedRows . ' stavki.');
    }
}

<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceStimulation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StimulationPagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

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

        $employeesQuery = $this->visibleEmployeeQuery($user);

        if ($canFilterByDepartment && $departmentId !== null) {
            $employeesQuery->where('dept', $departmentId);
        }

        $employees = $employeesQuery->get(['id', 'empID', 'firstName', 'lastName', 'dept']);
        $employeeIds = $employees->pluck('id');

        $records = AttendanceStimulation::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->get(['employee_id', 'work_date', 'minutes']);

        $stimulations = [];
        $totals = [];

        foreach ($records as $record) {
            $employeeId = (int) $record->employee_id;
            $dateKey = $record->work_date?->toDateString() ?? (string) $record->work_date;
            $minutes = (int) $record->minutes;

            if (!isset($stimulations[$employeeId][$dateKey])) {
                $stimulations[$employeeId][$dateKey] = [
                    'minutes' => 0,
                    'display' => '0:00',
                ];
            }

            $stimulations[$employeeId][$dateKey]['minutes'] += $minutes;
            $stimulations[$employeeId][$dateKey]['display'] = $this->formatMinutes(
                $stimulations[$employeeId][$dateKey]['minutes']
            );

            $totals[$employeeId] = ($totals[$employeeId] ?? 0) + $minutes;
        }

        foreach ($totals as $employeeId => $minutes) {
            $totals[$employeeId] = [
                'minutes' => $minutes,
                'display' => $this->formatMinutes($minutes),
            ];
        }

        return Inertia::render('HR/Stimulacija', [
            'month' => $monthParam,
            'days' => $days,
            'employees' => $employees->map(fn ($employee) => [
                'id' => (int) $employee->id,
                'empID' => (int) $employee->empID,
                'full_name' => trim((string) $employee->lastName . ' ' . (string) $employee->firstName),
                'department_id' => $employee->dept !== null ? (int) $employee->dept : null,
            ])->values()->all(),
            'stimulations' => $stimulations,
            'totals' => $totals,
            'canFilterByDepartment' => $canFilterByDepartment,
            'departments' => $canFilterByDepartment ? $this->departmentFilterOptions() : [],
            'filters' => [
                'department_id' => $departmentId !== null ? (string) $departmentId : '',
            ],
        ]);
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv(max($minutes, 0), 60);
        $remainingMinutes = max($minutes, 0) % 60;

        return sprintf('%d:%02d', $hours, $remainingMinutes);
    }
}

<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\OvertimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OvertimePagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    public function index(Request $request, OvertimeService $service)
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

        $employeesQuery = $this->scopedEmployeeQuery($user);

        if ($canFilterByDepartment && $departmentId !== null) {
            $employeesQuery->where('dept', $departmentId);
        }

        $employees = $employeesQuery->get(['id', 'empID', 'firstName', 'lastName', 'dept']);

        $employeeIds = $employees->pluck('id');

        $overview = $service->getMonthlyOverview($employeeIds, $monthStart, $monthEnd);

        return Inertia::render('HR/PrekovremeniSati', [
            'month' => $monthParam,
            'days' => $days,
            'employees' => $employees->map(fn ($employee) => [
                'id' => (int) $employee->id,
                'empID' => (int) $employee->empID,
                'full_name' => trim((string) $employee->lastName . ' ' . (string) $employee->firstName),
                'department_id' => $employee->dept !== null ? (int) $employee->dept : null,
            ])->values()->all(),
            'overtime' => $overview['overtime'],
            'totals' => $overview['totals'],
            'canFilterByDepartment' => $canFilterByDepartment,
            'departments' => $canFilterByDepartment ? $this->departmentFilterOptions() : [],
            'filters' => [
                'department_id' => $departmentId !== null ? (string) $departmentId : '',
            ],
        ]);
    }
}

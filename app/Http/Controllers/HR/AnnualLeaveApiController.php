<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\AnnualLeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnualLeaveApiController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    public function __construct(private readonly AnnualLeaveService $service)
    {
    }

    public function balance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $employeeId = (int) $validated['employee_id'];
        if ($response = $this->denyUnlessCanAccess($request, $employeeId)) {
            return $response;
        }

        $year = (int) ($validated['year'] ?? now()->year);

        return response()->json($this->service->getEmployeeYearBalance($employeeId, $year));
    }

    public function workingDays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $days = $this->service->calculateWorkingDays($validated['from'], $validated['to']);

        return response()->json([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'working_days' => $days,
        ]);
    }

    public function decisions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $employeeId = (int) $validated['employee_id'];
        if ($response = $this->denyUnlessCanAccess($request, $employeeId)) {
            return $response;
        }

        $year = (int) ($validated['year'] ?? now()->year);

        return response()->json($this->service->getDecisionsForEmployeeYear($employeeId, $year));
    }

    public function balanceDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $employeeId = (int) $validated['employee_id'];
        if ($response = $this->denyUnlessCanAccess($request, $employeeId)) {
            return $response;
        }

        $year = (int) ($validated['year'] ?? now()->year);

        return response()->json($this->service->getBalanceDetails($employeeId, $year));
    }

    public function balanceAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $employees = $this->scopedEmployeeRows($request);

        return response()->json($this->service->getBalanceAll($year, $employees));
    }

    public function balanceSummary(Request $request): JsonResponse
    {
        $employees = $this->scopedEmployeeRows($request);

        return response()->json($this->service->getBalanceSummary($employees));
    }

    public function balanceSummaryDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
        ]);

        $employeeId = (int) $validated['employee_id'];
        if ($response = $this->denyUnlessCanAccess($request, $employeeId)) {
            return $response;
        }

        return response()->json($this->service->getBalanceSummaryDetails($employeeId));
    }

    private function denyUnlessCanAccess(Request $request, int $employeeId): ?JsonResponse
    {
        if ($this->canAccessEmployee($request->user(), $employeeId)) {
            return null;
        }

        return response()->json(['message' => 'Nemate pravo pristupa ovom radniku.'], 403);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{employee_id: int, firstName: mixed, lastName: mixed}>
     */
    private function scopedEmployeeRows(Request $request)
    {
        return $this->scopedEmployeeQuery($request->user())
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn ($e) => (object) [
                'employee_id' => (int) $e->id,
                'firstName' => $e->firstName,
                'lastName' => $e->lastName,
            ])
            ->values();
    }
}

<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AnnualLeaveDecision;
use App\Models\Employee;
use App\Services\AnnualLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnualLeaveDecisionPagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    public function index(Request $request): Response
    {
        $employees = $this->scopedEmployeeQuery($request->user())
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => trim($e->lastName . ' ' . $e->firstName),
            ])
            ->values();

        return Inertia::render('HR/GodisnjiRjesenja', [
            'employees' => $employees,
            'defaultYear' => now()->year,
        ]);
    }

    public function store(Request $request, AnnualLeaveService $service): RedirectResponse
    {
        $nullableFields = [
            'decision_number',
            'decision_date',
            'valid_from',
            'valid_to',
            'note',
        ];

        foreach ($nullableFields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'part' => ['required', 'in:ljetni,zimski,jednodnevni,ostalo'],

            'decision_number' => ['nullable', 'string', 'max:50'],
            'decision_date' => ['nullable', 'date'],

            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],

            'granted_days' => ['required', 'integer', 'min:0'],

            'note' => ['nullable', 'string'],
        ]);

        $employeeId = (int) $validated['employee_id'];

        if (!$this->canAccessEmployee($request->user(), $employeeId)) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $year = (int) $validated['year'];

        $hasAnyDecisionThisYear = AnnualLeaveDecision::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->exists();

        $carryOver = 0;
        if (!$hasAnyDecisionThisYear) {
            $prev = $service->getEmployeeYearBalance($employeeId, $year - 1);
            $carryOver = (int) max(0, (int) ($prev['remaining_days'] ?? 0));
        }

        // Carryover is applied automatically only once per year (on the first created decision).
        $validated['carried_over_days'] = $carryOver;
        $validated['created_by_user_id'] = $request->user()?->id;

        AnnualLeaveDecision::create($validated);

        return redirect()
            ->route('hr.godisnji.rjesenja')
            ->banner('Rješenje je uspješno sačuvano.');
    }
}

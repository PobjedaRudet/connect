<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AnnualLeaveDecision;
use App\Models\AnnualLeaveUsage;
use App\Models\Employee;
use App\Services\AnnualLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnualLeaveUsagePagesController extends Controller
{
    public function index(): Response
    {
        $employees = Employee::query()
            ->where(function ($q) {
                $q->whereNull('Active')->orWhere('Active', '=', 1);
            })
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => trim($e->lastName . ' ' . $e->firstName),
            ])
            ->values();

        return Inertia::render('HR/GodisnjiIskoristenje', [
            'employees' => $employees,
            'defaultYear' => now()->year,
        ]);
    }

    public function store(Request $request, AnnualLeaveService $service): RedirectResponse
    {
        foreach (['note'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_leave_decision_id' => ['required', 'integer', 'exists:annual_leave_decisions,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'note' => ['nullable', 'string'],
        ]);

        $employeeId = (int) $validated['employee_id'];
        $year = (int) $validated['year'];
        $decisionId = (int) $validated['annual_leave_decision_id'];

        $decision = AnnualLeaveDecision::query()
            ->where('id', $decisionId)
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->first();

        if (!$decision) {
            return back()->withErrors([
                'annual_leave_decision_id' => 'Izabrano rješenje ne pripada ovom radniku ili ovoj godini.',
            ]);
        }

        $days = (int) round($service->calculateWorkingDays($validated['date_from'], $validated['date_to']), 0);

        $balance = $service->getDecisionBalance($decision);
        $remaining = (int) ($balance['remaining_days'] ?? 0);

        if ($days > $remaining) {
            return back()->withErrors([
                'date_to' => 'Nema dovoljno preostalih dana na izabranom rješenju (preostalo: ' . $remaining . ').',
            ]);
        }

        AnnualLeaveUsage::create([
            'employee_id' => $employeeId,
            'annual_leave_decision_id' => $decisionId,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'days' => $days,
            'note' => $validated['note'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('hr.godisnji.iskoristenje')
            ->banner('Iskorištenje godišnjeg je uspješno sačuvano.');
    }
}

<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SickLeave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SickLeavePagesController extends Controller
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

        $rows = SickLeave::query()
            ->with(['employee:id,firstName,lastName'])
            ->orderByDesc('from')
            ->limit(50)
            ->get()
            ->map(fn (SickLeave $s) => [
                'id' => $s->id,
                'employee_id' => $s->employee_id,
                'employee_name' => $s->employee
                    ? trim($s->employee->lastName . ' ' . $s->employee->firstName)
                    : null,
                'from' => $s->from?->toDateString(),
                'to' => $s->to?->toDateString(),
                'days' => $s->days,
                'status' => $s->status,
                'document_number' => $s->document_number,
            ])
            ->values();

        return Inertia::render('HR/Bolovanja', [
            'employees' => $employees,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $nullableFields = [
            'leave_id',
            'days',
            'document_number',
            'document_date',
            'doctor',
            'diagnosis_code',
            'note',
        ];

        foreach ($nullableFields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_id' => ['nullable', 'integer'],

            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'days' => ['nullable', 'integer', 'min:0'],

            'document_number' => ['nullable', 'string', 'max:50'],
            'document_date' => ['nullable', 'date'],

            'doctor' => ['nullable', 'string', 'max:150'],
            'diagnosis_code' => ['nullable', 'string', 'max:50'],

            'note' => ['nullable', 'string'],
            'status' => ['nullable', 'in:otvoreno,zatvoreno'],
        ]);

        if (!$this->canAccessEmployee($request->user(), (int) $validated['employee_id'])) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $validated['status'] = $validated['status'] ?? 'otvoreno';
        $validated['created_by_user_id'] = $request->user()?->id;

        SickLeave::create($validated);

        return redirect()
            ->route('hr.bolovanja')
            ->banner('Bolovanje je uspješno sačuvano.');
    }
}

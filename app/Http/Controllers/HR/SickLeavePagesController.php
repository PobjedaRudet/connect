<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\SickLeave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SickLeavePagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    private const DELETE_PIN = '0000';

    public function index(Request $request): Response
    {
        $employees = $this->scopedEmployeeQuery($request->user())
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => trim($e->lastName . ' ' . $e->firstName),
            ])
            ->values();

        $rowsQuery = SickLeave::query()
            ->with(['employee:id,firstName,lastName']);

        if (! $this->hasGlobalEmployeeAccess($request->user())) {
            $rowsQuery->whereIn('employee_id', $this->visibleEmployeeQuery($request->user())->pluck('id'));
        }

        $rows = $rowsQuery
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
                'document_date' => $s->document_date?->toDateString(),
                'note' => $s->note,
            ])
            ->values();

        return Inertia::render('HR/Bolovanja', [
            'employees' => $employees,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSickLeavePayload($request);

        if (!$this->canAccessEmployee($request->user(), (int) $validated['employee_id'])) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $validated['created_by_user_id'] = $request->user()?->id;

        SickLeave::create($validated);

        return redirect()
            ->route('hr.bolovanja')
            ->banner('Bolovanje je uspješno sačuvano.');
    }

    public function update(Request $request, SickLeave $sickLeave): RedirectResponse
    {
        if (!$this->canAccessEmployee($request->user(), (int) $sickLeave->employee_id)) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $validated = $this->validateSickLeavePayload($request);

        if (!$this->canAccessEmployee($request->user(), (int) $validated['employee_id'])) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $sickLeave->update($validated);

        return redirect()
            ->route('hr.bolovanja')
            ->banner('Bolovanje je uspješno ažurirano.');
    }

    public function destroy(Request $request, SickLeave $sickLeave): RedirectResponse
    {
        if (!$this->canAccessEmployee($request->user(), (int) $sickLeave->employee_id)) {
            return back()->withErrors(['pin' => 'Nemate pristup odabranom radniku.']);
        }

        $request->validate([
            'pin' => ['required', 'string', Rule::in([self::DELETE_PIN])],
        ], [
            'pin.required' => 'Unesite šifru za brisanje.',
            'pin.in' => 'Pogrešna šifra za brisanje.',
        ]);

        $sickLeave->delete();

        return redirect()
            ->route('hr.bolovanja')
            ->banner('Bolovanje je obrisano.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSickLeavePayload(Request $request): array
    {
        $nullableFields = [
            'leave_id',
            'days',
            'document_number',
            'document_date',
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

            'note' => ['nullable', 'string'],
            'status' => ['nullable', 'in:otvoreno,zatvoreno'],
        ]);

        $validated['status'] = $validated['status'] ?? 'otvoreno';

        return $validated;
    }
}

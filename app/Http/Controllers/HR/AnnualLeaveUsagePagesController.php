<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AnnualLeaveDecision;
use App\Models\AnnualLeaveUsage;
use App\Services\AnnualLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnualLeaveUsagePagesController extends Controller
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

        $rowsQuery = AnnualLeaveUsage::query()
            ->with([
                'employee:id,firstName,lastName',
                'decision:id,year,part,decision_number',
            ]);

        if (! $this->hasGlobalEmployeeAccess($request->user())) {
            $rowsQuery->whereIn('employee_id', $this->visibleEmployeeQuery($request->user())->pluck('id'));
        }

        $rows = $rowsQuery
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AnnualLeaveUsage $usage) => [
                'id' => $usage->id,
                'employee_id' => $usage->employee_id,
                'employee_name' => $usage->employee
                    ? trim($usage->employee->lastName . ' ' . $usage->employee->firstName)
                    : null,
                'year' => $usage->decision?->year,
                'annual_leave_decision_id' => $usage->annual_leave_decision_id,
                'decision_label' => trim(implode(' ', array_filter([
                    $this->partLabel($usage->decision?->part),
                    $usage->decision?->decision_number ? ('#' . $usage->decision->decision_number) : null,
                ]))),
                'date_from' => $usage->date_from?->toDateString(),
                'date_to' => $usage->date_to?->toDateString(),
                'days' => (int) round((float) $usage->days, 0),
                'note' => $usage->note,
            ])
            ->values();

        return Inertia::render('HR/GodisnjiIskoristenje', [
            'employees' => $employees,
            'defaultYear' => now()->year,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request, AnnualLeaveService $service): RedirectResponse
    {
        $payload = $this->validatedUsage($request, $service);
        $payload['created_by_user_id'] = $request->user()?->id;

        AnnualLeaveUsage::create($payload);

        return redirect()
            ->route('hr.godisnji.iskoristenje')
            ->banner('Iskorištenje godišnjeg je uspješno sačuvano.');
    }

    public function update(Request $request, AnnualLeaveUsage $usage, AnnualLeaveService $service): RedirectResponse
    {
        if (! $this->canAccessEmployee($request->user(), (int) $usage->employee_id)) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $usage->update($this->validatedUsage($request, $service, $usage));

        return redirect()
            ->route('hr.godisnji.iskoristenje')
            ->banner('Iskorištenje godišnjeg je uspješno ažurirano.');
    }

    public function destroy(Request $request, AnnualLeaveUsage $usage): RedirectResponse
    {
        if (! $this->canAccessEmployee($request->user(), (int) $usage->employee_id)) {
            return back()->withErrors(['pin' => 'Nemate pristup odabranom radniku.']);
        }

        $request->validate([
            'pin' => ['required', 'string', Rule::in([self::DELETE_PIN])],
        ], [
            'pin.required' => 'Unesite šifru za brisanje.',
            'pin.in' => 'Pogrešna šifra za brisanje.',
        ]);

        $usage->delete();

        return redirect()
            ->route('hr.godisnji.iskoristenje')
            ->banner('Iskorištenje godišnjeg je obrisano.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUsage(Request $request, AnnualLeaveService $service, ?AnnualLeaveUsage $existing = null): array
    {
        if ($request->has('note') && $request->input('note') === '') {
            $request->merge(['note' => null]);
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

        if (! $this->canAccessEmployee($request->user(), $employeeId)) {
            throw ValidationException::withMessages([
                'employee_id' => 'Nemate pristup odabranom radniku.',
            ]);
        }

        $decision = AnnualLeaveDecision::query()
            ->where('id', (int) $validated['annual_leave_decision_id'])
            ->where('employee_id', $employeeId)
            ->where('year', (int) $validated['year'])
            ->first();

        if (! $decision) {
            throw ValidationException::withMessages([
                'annual_leave_decision_id' => 'Izabrano rješenje ne pripada ovom radniku ili ovoj godini.',
            ]);
        }

        $days = (int) round($service->calculateWorkingDays($validated['date_from'], $validated['date_to']), 0);

        $balance = $service->getDecisionBalance($decision);
        $remaining = (int) ($balance['remaining_days'] ?? 0);

        if ($existing && (int) $existing->annual_leave_decision_id === (int) $decision->id) {
            $remaining += (int) round((float) $existing->days, 0);
        }

        if ($days > $remaining) {
            throw ValidationException::withMessages([
                'date_to' => 'Nema dovoljno preostalih dana na izabranom rješenju (preostalo: ' . $remaining . ').',
            ]);
        }

        return [
            'employee_id' => $employeeId,
            'annual_leave_decision_id' => (int) $decision->id,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'days' => $days,
            'note' => $validated['note'] ?? null,
        ];
    }

    private function partLabel(?string $part): string
    {
        return match ($part) {
            'ljetni' => 'I dio',
            'zimski' => 'II dio',
            'jednodnevni' => 'Jednodnevni',
            'ostalo' => 'Ostalo',
            default => (string) $part,
        };
    }
}

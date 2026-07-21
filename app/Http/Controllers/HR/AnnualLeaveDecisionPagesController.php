<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AnnualLeaveDecision;
use App\Services\AnnualLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnualLeaveDecisionPagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    private const DELETE_PIN = '0000';
    private const LIST_PER_PAGE = 20;

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

    public function lista(Request $request): Response
    {
        $user = $request->user();

        $employees = $this->scopedEmployeeQuery($user)
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => trim($e->lastName . ' ' . $e->firstName),
            ])
            ->values();

        $accessibleEmployeeIds = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rowsQuery = AnnualLeaveDecision::query()
            ->with(['employee:id,firstName,lastName'])
            ->withSum('usages as used_days_sum', 'days')
            ->orderByDesc('year')
            ->orderByDesc('id');

        if (!$this->hasGlobalEmployeeAccess($user)) {
            if (empty($accessibleEmployeeIds)) {
                $rowsQuery->whereRaw('1 = 0');
            } else {
                $rowsQuery->whereIn('employee_id', $accessibleEmployeeIds);
            }
        }

        $decisions = $rowsQuery
            ->paginate(self::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (AnnualLeaveDecision $d) => [
                'id' => (int) $d->id,
                'employee_id' => (int) $d->employee_id,
                'employee_name' => $d->employee
                    ? trim($d->employee->lastName . ' ' . $d->employee->firstName)
                    : null,
                'year' => (int) $d->year,
                'part' => (string) $d->part,
                'decision_number' => $d->decision_number,
                'decision_date' => $d->decision_date?->toDateString(),
                'valid_from' => $d->valid_from?->toDateString(),
                'valid_to' => $d->valid_to?->toDateString(),
                'granted_days' => (int) round((float) $d->granted_days, 0),
                'carried_over_days' => (int) round((float) $d->carried_over_days, 0),
                'used_days' => (int) round((float) ($d->used_days_sum ?? 0), 0),
                'note' => $d->note,
            ]);

        return Inertia::render('HR/GodisnjiRjesenjaLista', [
            'employees' => $employees,
            'decisions' => $decisions,
        ]);
    }

    public function store(Request $request, AnnualLeaveService $service): RedirectResponse
    {
        $validated = $this->validateDecisionPayload($request);

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
            ->route('hr.godisnji.rjesenja.lista')
            ->banner('Rješenje je uspješno sačuvano.');
    }

    public function update(
        Request $request,
        AnnualLeaveDecision $decision,
        AnnualLeaveService $service
    ): RedirectResponse {
        if (!$this->canAccessEmployee($request->user(), (int) $decision->employee_id)) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $validated = $this->validateDecisionPayload($request);

        $employeeId = (int) $validated['employee_id'];

        if (!$this->canAccessEmployee($request->user(), $employeeId)) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $balance = $service->getDecisionBalance($decision);
        $usedDays = (int) ($balance['used_days'] ?? 0);
        $newTotal = (int) $validated['granted_days'] + (int) round((float) $decision->carried_over_days, 0);

        if ($newTotal < $usedDays) {
            throw ValidationException::withMessages([
                'granted_days' => 'Odobreni dani ne mogu biti manji od već iskorištenih (' . $usedDays . ').',
            ]);
        }

        $decision->update([
            'employee_id' => $employeeId,
            'year' => (int) $validated['year'],
            'part' => $validated['part'],
            'decision_number' => $validated['decision_number'] ?? null,
            'decision_date' => $validated['decision_date'] ?? null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_to' => $validated['valid_to'] ?? null,
            'granted_days' => $validated['granted_days'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('hr.godisnji.rjesenja.lista', $request->only('page'))
            ->banner('Rješenje je uspješno ažurirano.');
    }

    public function destroy(Request $request, AnnualLeaveDecision $decision): RedirectResponse
    {
        if (!$this->canAccessEmployee($request->user(), (int) $decision->employee_id)) {
            return back()->withErrors(['pin' => 'Nemate pristup odabranom radniku.']);
        }

        $this->validateDeletePin($request);

        $label = trim(implode(' · ', array_filter([
            $decision->decision_number ? ('Br. ' . $decision->decision_number) : null,
            $decision->year ? ((string) $decision->year . '.') : null,
            $decision->part ? (string) $decision->part : null,
        ])));

        $decision->delete();

        return redirect()
            ->route('hr.godisnji.rjesenja.lista', $request->only('page'))
            ->banner('Rješenje' . ($label !== '' ? ' (' . $label . ')' : '') . ' je obrisano.');
    }

    private function validateDeletePin(Request $request): void
    {
        $request->validate([
            'pin' => ['required', 'string', Rule::in([self::DELETE_PIN])],
        ], [
            'pin.required' => 'Unesite šifru za brisanje.',
            'pin.in' => 'Pogrešna šifra za brisanje.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDecisionPayload(Request $request): array
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

        return $request->validate([
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
    }
}

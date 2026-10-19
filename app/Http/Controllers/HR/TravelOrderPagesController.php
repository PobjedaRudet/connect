<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\TravelOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TravelOrderPagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    public function index(Request $request): Response
    {
        $employees = $this->scopedEmployeeQuery($request->user())
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => trim($e->lastName . ' ' . $e->firstName),
            ])
            ->values();

        $rowsQuery = TravelOrder::query()->with('employee:id,firstName,lastName');

        if (! $this->hasGlobalEmployeeAccess($request->user())) {
            $rowsQuery->whereIn('employee_id', $this->scopedEmployeeQuery($request->user())->pluck('id'));
        }

        $rows = $rowsQuery
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (TravelOrder $order) => [
                'id' => $order->id,
                'employee_id' => (int) $order->employee_id,
                'employee_name' => $order->employee
                    ? trim($order->employee->lastName . ' ' . $order->employee->firstName)
                    : null,
                'date_from' => $order->date_from?->toDateString(),
                'date_to' => $order->date_to?->toDateString(),
                'reason' => $order->reason,
            ])
            ->values();

        return Inertia::render('HR/PutniNalozi', [
            'employees' => $employees,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'date_to.after_or_equal' => 'Datum povratka mora biti isti ili poslije datuma polaska.',
            'reason.required' => 'Razlog je obavezan.',
        ]);

        if (! $this->canAccessEmployee($request->user(), (int) $validated['employee_id'])) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $this->assertNoOverlap(
            (int) $validated['employee_id'],
            $validated['date_from'],
            $validated['date_to']
        );

        TravelOrder::create([
            'employee_id' => (int) $validated['employee_id'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'reason' => trim($validated['reason']),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('hr.putni-nalozi')
            ->banner('Putni nalog je sačuvan.');
    }

    public function destroy(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        if (! $this->canAccessEmployee($request->user(), (int) $travelOrder->employee_id)) {
            return back()->withErrors(['employee_id' => 'Nemate pristup odabranom radniku.']);
        }

        $travelOrder->delete();

        return redirect()
            ->route('hr.putni-nalozi')
            ->banner('Putni nalog je obrisan.');
    }

    private function assertNoOverlap(int $employeeId, string $dateFrom, string $dateTo): void
    {
        $overlaps = TravelOrder::query()
            ->where('employee_id', $employeeId)
            ->where('date_from', '<=', $dateTo)
            ->where('date_to', '>=', $dateFrom)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'date_from' => 'Putni nalog se preklapa sa postojećim nalogom istog radnika.',
            ]);
        }
    }
}

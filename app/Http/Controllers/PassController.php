<?php

namespace App\Http\Controllers;

use App\Models\Pass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PassController extends Controller
{
    /**
     * Public page: list all open passes.
     */
    public function active(): Response
    {
        $passes = Pass::with('employee')
            ->whereNull('approved_by')
            ->orderByRaw("status = 'open' desc")
            ->orderByDesc('start_time')
            ->get()
            ->map(function (Pass $pass) {
                $employee = $pass->employee;
                $fullName = $employee
                    ? trim(($employee->firstName ?? '') . ' ' . ($employee->lastName ?? ''))
                    : 'Nepoznat';

                return [
                    'id' => $pass->id,
                    'employee_name' => $fullName,
                    'employee_id' => $pass->employee_id,
                    'type' => $pass->type,
                    'reason' => $pass->reason,
                    'start_time' => optional($pass->start_time)->toDateTimeString(),
                    'end_time' => optional($pass->end_time)->toDateTimeString(),
                    'status' => $pass->status,
                    'approved' => (bool) $pass->approved,
                ];
            });

        return inertia('HR/OdobravanjeIzlaznica', [
            'passes' => $passes,
            'workdayEndTime' => config('app.workday_end_time', '15:00'),
        ]);
    }

    /**
     * Update pass type (privatni | službeni) while still open.
     */
    public function updateType(Request $request, Pass $pass): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:privatni,službeni',
        ]);

        if ($pass->approved) {
            return back()->with('info', 'Izlaznica je već odobrena.');
        }

        $pass->update(['type' => $validated['type']]);

        return back()->with('success', 'Tip izlaznice ažuriran.');
    }

    /**
     * Confirm pass: set type and mark approved, without closing or computing duration.
     */
    public function confirm(Request $request, Pass $pass): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:privatni,službeni',
        ]);

        $pass->update([
            'type' => $validated['type'],
            'approved' => true,
        ]);

        return back()->with('success', 'Izlaznica označena kao odobrena.');
    }
}

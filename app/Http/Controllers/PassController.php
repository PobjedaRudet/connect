<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Pass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Inertia\Response;

class PassController extends Controller
{
    private function resolveApproverEmployeeId(?User $user): ?int
    {
        $email = trim((string) ($user?->email ?? ''));
        if ($email === '') {
            return null;
        }

        return Employee::query()
            ->where('email', $email)
            ->value('id');
    }

    private function isAdminUser($user): bool
    {
        return (bool) (($user?->isadmin ?? false) || ($user?->is_admin ?? false));
    }

    private function scopePassesBySupervisor(Builder $query, $user): Builder
    {
        if (!$user || $this->isAdminUser($user)) {
            return $query;
        }

        $uid = (int) $user->id;

        return $query->whereHas('employee', function ($q) use ($uid) {
            $q->whereJsonContains('pass_approvers', $uid)
                ->orWhereJsonContains('pass_approvers', (string) $uid);
        });
    }

    private function canAccessPass($user, Pass $pass): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isAdminUser($user)) {
            return true;
        }

        return Employee::where('id', (int) $pass->employee_id)
            ->where(function ($q) use ($user) {
                $q->whereJsonContains('pass_approvers', (int) $user->id)
                    ->orWhereJsonContains('pass_approvers', (string) $user->id);
            })
            ->exists();
    }

    /**
     * Public page: list all open passes.
     */
    public function active(Request $request): Response
    {
        $passesQuery = Pass::with('employee')
            ->whereNull('approved_by')
            ->where('approved', false)
            ->orderByRaw("status = 'open' desc")
            ->orderByDesc('start_time')
            ;

        $passes = $this->scopePassesBySupervisor($passesQuery, $request->user())
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
                    'duration_minutes' => $pass->duration_minutes,
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
     * List approved passes for the selected month (defaults to current month).
     */
    public function approved(Request $request): Response
    {
        $monthInput = $request->query('month');

        $selectedMonth = rescue(
            fn () => Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth(),
            now()->startOfMonth(),
            false
        );

        $start = $selectedMonth->copy();
        $end = $selectedMonth->copy()->endOfMonth();

        $passesQuery = Pass::with(['employee', 'approverUser'])
            ->where('approved', true)
            ->whereBetween('start_time', [$start, $end])
            ->orderByDesc('start_time')
            ;

        $passes = $this->scopePassesBySupervisor($passesQuery, $request->user())
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
                    'duration_minutes' => $pass->duration_minutes,
                    'approved_at' => optional($pass->updated_at)->toDateTimeString(),
                    'approved_by_name' => $pass->approverUser?->name,
                ];
            });

        $availableMonths = collect(range(0, 11))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset)->format('Y-m'));

        return inertia('HR/OdobreneIzlaznice', [
            'passes' => $passes,
            'selectedMonth' => $start->format('Y-m'),
            'availableMonths' => $availableMonths,
        ]);
    }

    /**
     * Update pass type (privatni | službeni) while still open.
     */
    public function updateType(Request $request, Pass $pass): RedirectResponse
    {
        if (!$this->canAccessPass($request->user(), $pass)) {
            abort(403, 'Nemate pravo pristupa ovoj izlaznici.');
        }

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
        if (!$this->canAccessPass($request->user(), $pass)) {
            abort(403, 'Nemate pravo pristupa ovoj izlaznici.');
        }

        $validated = $request->validate([
            'type' => 'required|in:privatni,službeni',
        ]);

        if ($pass->approved) {
            return back()->with('info', 'Izlaznica je već odobrena.');
        }

        $approverUser = $request->user();

        $pass->update([
            'type' => $validated['type'],
            'approved' => true,
            'approved_by' => $this->resolveApproverEmployeeId($approverUser),
            'approved_by_user_id' => $approverUser?->id,
        ]);

        return back()->with('success', 'Izlaznica označena kao odobrena.');
    }
}

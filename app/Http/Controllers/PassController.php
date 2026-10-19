<?php

namespace App\Http\Controllers;

use App\Jobs\SendPassCreatedEmailJob;
use App\Models\AttendanceRecord;
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
    private const MANUAL_REASON = 'Ručni unos zbog zaboravljenog skena';

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
                    'start_time' => $pass->start_time?->format('Y-m-d H:i:s'),
                    'end_time' => $pass->end_time?->format('Y-m-d H:i:s'),
                    'duration_minutes' => $pass->duration_minutes,
                    'status' => $pass->status,
                    'approved' => (bool) $pass->approved,
                ];
            });

        return inertia('HR/OdobravanjeIzlaznica', [
            'passes' => $passes,
            'employees' => $this->employeesForManualPass($request->user()),
            'workdayEndTime' => config('app.workday_end_time', '15:00'),
        ]);
    }

    /**
     * Ručni unos zatvorene izlaznice za zaboravljeni sken u toku smjene.
     * Ne dira prijavu, odjavu, kapiju ni automatske izlaznice za kašnjenje.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'type' => 'required|in:privatni,službeni',
            'reason' => 'required|string|max:2000',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ], [
            'employee_id.required' => 'Odaberite radnika.',
            'type.required' => 'Odaberite tip izlaznice.',
            'reason.required' => 'Razlog je obavezan.',
            'start_time.required' => 'Vrijeme izlaska je obavezno.',
            'end_time.required' => 'Vrijeme povratka je obavezno.',
            'end_time.after' => 'Povratak mora biti poslije izlaska.',
        ]);

        $employee = Employee::query()->findOrFail((int) $validated['employee_id']);
        if (! $this->canCreatePassFor($request->user(), $employee)) {
            abort(403, 'Nemate pravo unosa izlaznice za ovog radnika.');
        }

        $tz = config('app.timezone');
        $start = Carbon::parse($validated['start_time'], $tz);
        $end = Carbon::parse($validated['end_time'], $tz);
        $now = Carbon::now($tz);

        if ($start->greaterThan($now) || $end->greaterThan($now)) {
            return back()->withErrors([
                'end_time' => 'Izlaznica ne može biti u budućnosti.',
            ])->withInput();
        }

        if (! $this->intervalInsideAttendance((int) $employee->id, $start, $end, $now)) {
            return back()->withErrors([
                'start_time' => 'Interval mora pasti unutar jedne prijave tog radnika. Ako je prijava još otvorena, povratak mora biti do sada.',
            ])->withInput();
        }

        if ($this->overlapsExistingPass((int) $employee->id, $start, $end)) {
            return back()->withErrors([
                'start_time' => 'Izlaznica se preklapa sa postojećom izlaznicom ovog radnika.',
            ])->withInput();
        }

        $pass = Pass::create([
            'employee_id' => $employee->id,
            'type' => $validated['type'],
            'reason' => $this->manualReason($validated['reason']),
            'start_time' => $start,
            'end_time' => $end,
            'approved_by' => null,
            'approved_by_user_id' => null,
            'status' => 'closed',
            'duration_minutes' => $this->durationMinutes($validated['type'], $start, $end),
            'approved' => false,
            'late_pass' => false,
            'late_minutes' => null,
            'early_departure' => false,
            'early_minutes' => null,
        ]);

        SendPassCreatedEmailJob::dispatch($employee->id, $pass->id)->afterResponse();

        return back()->banner('Izlaznica je unesena.');
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
                    'start_time' => $pass->start_time?->format('Y-m-d H:i:s'),
                    'end_time' => $pass->end_time?->format('Y-m-d H:i:s'),
                    'duration_minutes' => $pass->duration_minutes,
                    'approved_at' => $pass->updated_at?->format('Y-m-d H:i:s'),
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

    public function update(Request $request, Pass $pass): RedirectResponse
    {
        if (! $this->canAccessPass($request->user(), $pass)) {
            abort(403, 'Nemate pravo pristupa ovoj izlaznici.');
        }

        if ($pass->approved) {
            return back()->with('info', 'Odobrena izlaznica se ne može mijenjati.');
        }

        if ($request->has('end_time') && $request->input('end_time') === '') {
            $request->merge(['end_time' => null]);
        }

        if ($request->has('reason') && $request->input('reason') === null) {
            $request->merge(['reason' => '']);
        }

        $validated = $request->validate([
            'type' => 'required|in:privatni,službeni',
            'reason' => 'nullable|string|max:2000',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
        ]);

        $endTime = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : null;
        $startTime = Carbon::parse($validated['start_time']);
        $duration = $endTime
            ? $this->durationMinutes($validated['type'], $startTime, $endTime)
            : null;

        $updates = [
            'type' => $validated['type'],
            'reason' => $validated['reason'] ?? '',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $endTime ? 'closed' : 'open',
            'duration_minutes' => $duration,
        ];

        if ($pass->late_pass) {
            $updates['late_minutes'] = $duration;
        }

        if ($pass->early_departure) {
            $updates['early_minutes'] = $duration;
        }

        $pass->update($updates);

        return back()->banner('Izlaznica je ažurirana.');
    }

    public function destroy(Request $request, Pass $pass): RedirectResponse
    {
        if (! $this->canAccessPass($request->user(), $pass)) {
            abort(403, 'Nemate pravo pristupa ovoj izlaznici.');
        }

        if ($pass->approved) {
            return back()->with('info', 'Odobrena izlaznica se ne može obrisati.');
        }

        $pass->delete();

        return back()->banner('Izlaznica je obrisana.');
    }

    private function canCreatePassFor($user, Employee $employee): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isAdminUser($user)) {
            return true;
        }

        $uid = (int) $user->id;
        foreach ((array) ($employee->pass_approvers ?? []) as $approverId) {
            if ((int) $approverId === $uid) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function employeesForManualPass($user): array
    {
        $query = Employee::query()
            ->where(function ($q) {
                $q->whereNull('Active')->orWhere('Active', 1);
            })
            ->orderBy('lastName')
            ->orderBy('firstName');

        if (! $this->isAdminUser($user)) {
            $uid = (int) ($user->id ?? 0);
            $query->where(function ($q) use ($uid) {
                $q->whereJsonContains('pass_approvers', $uid)
                    ->orWhereJsonContains('pass_approvers', (string) $uid);
            });
        }

        return $query->get(['id', 'firstName', 'lastName'])
            ->map(fn (Employee $employee) => [
                'id' => (int) $employee->id,
                'name' => trim((string) $employee->lastName . ' ' . (string) $employee->firstName),
            ])
            ->values()
            ->all();
    }

    private function manualReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || $reason === self::MANUAL_REASON) {
            return self::MANUAL_REASON;
        }

        if (str_starts_with($reason, self::MANUAL_REASON)) {
            return $reason;
        }

        return self::MANUAL_REASON . '. ' . $reason;
    }

    private function intervalInsideAttendance(int $employeeId, Carbon $start, Carbon $end, Carbon $now): bool
    {
        $tz = config('app.timezone');

        $records = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereNotNull('entry_time')
            ->where(function ($q) use ($end) {
                $q->where('entry_time', '<=', $end)
                    ->orWhere(function ($q) use ($end) {
                        $q->whereNotNull('effective_start')->where('effective_start', '<=', $end);
                    });
            })
            ->where(function ($q) use ($start) {
                $q->whereNull('exit_time')->orWhere('exit_time', '>=', $start);
            })
            ->get(['entry_time', 'effective_start', 'exit_time']);

        foreach ($records as $record) {
            $windowStart = $record->effective_start ?: $record->entry_time;
            if (! $windowStart) {
                continue;
            }

            $windowStart = Carbon::parse($windowStart)->timezone($tz);
            $windowEnd = $record->exit_time
                ? Carbon::parse($record->exit_time)->timezone($tz)
                : $now;

            if ($start->greaterThanOrEqualTo($windowStart) && $end->lessThanOrEqualTo($windowEnd)) {
                return true;
            }
        }

        return false;
    }

    private function overlapsExistingPass(int $employeeId, Carbon $start, Carbon $end): bool
    {
        return Pass::query()
            ->where('employee_id', $employeeId)
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($q) use ($start, $end) {
                    $q->whereNotNull('end_time')
                        ->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                })->orWhere(function ($q) use ($end) {
                    $q->whereNull('end_time')
                        ->where('start_time', '<', $end);
                });
            })
            ->exists();
    }

    private function durationMinutes(string $type, Carbon $start, Carbon $end): int
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $endReference = $end;

        if ($type === 'privatni') {
            $workdayEndString = config('app.workday_end_time', '15:00');

            try {
                $workdayEnd = Carbon::parse($start->toDateString() . ' ' . $workdayEndString);
            } catch (\Throwable) {
                $workdayEnd = $start->copy()->setTime(15, 0);
            }

            if ($workdayEnd->greaterThan($start) && $end->greaterThan($workdayEnd)) {
                $endReference = $workdayEnd;
            }
        }

        return (int) max($start->diffInMinutes($endReference, false), 0);
    }
}

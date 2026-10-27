<?php

namespace App\Services;

use App\Mail\AutoShiftCloseMail;
use App\Models\AttendanceRecord;
use App\Models\AutoShiftCloseNotification;
use App\Models\Employee;
use App\Models\GateLog;
use App\Models\Pass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class AutoShiftCloseService
{
    /**
     * @return array{closed_terminal: int, closed_gate: int, closed_passes: int, mails_queued: int}
     */
    public function close(string $shiftCode, ?Carbon $now = null): array
    {
        $shiftCode = strtoupper(trim($shiftCode));
        if (!in_array($shiftCode, ['I', 'II', 'III'], true)) {
            throw new InvalidArgumentException('Smjena mora biti I, II ili III.');
        }

        $tz = config('app.timezone');
        $now = ($now ?? Carbon::now($tz))->copy()->timezone($tz);
        $cutoff = $this->cutoffFor($shiftCode, $now);
        $windowStart = $this->windowStart($shiftCode, $cutoff);

        $records = AttendanceRecord::query()
            ->with(['employee:id,firstName,lastName,rfid_code,nadlezne_osobe', 'shift'])
            ->whereHas('shift', function ($query) use ($shiftCode) {
                $query->whereRaw('UPPER(TRIM(attendance_credit_code)) = ?', [$shiftCode]);
            })
            ->where('entry_time', '>=', $windowStart)
            ->where('entry_time', '<', $cutoff)
            ->orderBy('id')
            ->get();

        Log::info('app:auto-shift-close candidates', [
            'shift' => $shiftCode,
            'window_start' => $windowStart->toDateTimeString(),
            'cutoff' => $cutoff->toDateTimeString(),
            'count' => $records->count(),
        ]);

        $closedTerminal = 0;
        $closedGate = 0;
        $closedPasses = 0;
        $actions = [];

        foreach ($records->groupBy('employee_id') as $employeeId => $employeeRecords) {
            try {
                $action = DB::transaction(function () use ($employeeId, $employeeRecords, $windowStart, $cutoff) {
                    return $this->closeEmployee((int) $employeeId, $employeeRecords, $windowStart, $cutoff);
                });
            } catch (\Throwable $e) {
                Log::error('Automatska odjava nije upisana.', [
                    'employee_id' => (int) $employeeId,
                    'shift' => $shiftCode,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($action === null) {
                continue;
            }

            $closedTerminal += $action['terminal'];
            $closedGate += $action['gate'];
            $closedPasses += $action['passes'];
            $actions[] = $action;
        }

        $queued = $this->queueMails($actions, $shiftCode, $cutoff);

        Log::info('app:auto-shift-close finished', [
            'shift' => $shiftCode,
            'closed_terminal' => $closedTerminal,
            'closed_gate' => $closedGate,
            'closed_passes' => $closedPasses,
            'mails_queued' => $queued,
        ]);

        return [
            'closed_terminal' => $closedTerminal,
            'closed_gate' => $closedGate,
            'closed_passes' => $closedPasses,
            'mails_queued' => $queued,
        ];
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $seedRecords
     * @return array{employee: Employee, terminal: int, gate: int, passes: int, missing: string}|null
     */
    private function closeEmployee(int $employeeId, Collection $seedRecords, Carbon $windowStart, Carbon $cutoff): ?array
    {
        $records = AttendanceRecord::query()
            ->with('shift')
            ->whereIn('id', $seedRecords->pluck('id')->all())
            ->where('employee_id', $employeeId)
            ->lockForUpdate()
            ->get();

        $employee = Employee::query()->find($employeeId);
        if (!$employee) {
            Log::warning('Automatska odjava: radnik nije pronađen.', ['employee_id' => $employeeId]);
            return null;
        }

        $terminalCount = 0;
        foreach ($records as $record) {
            if ($record->exit_time !== null) {
                continue;
            }

            $record->update([
                'exit_time' => $cutoff,
                'status' => 'left',
                'terminal_out' => AttendanceRecord::AUTO_SHIFT_CLOSE_TERMINAL,
            ]);
            $terminalCount++;
        }

        $closedGate = $this->closeGateIfOpen($employee, $windowStart, $cutoff);
        if ($terminalCount === 0 && !$closedGate) {
            return null;
        }

        return [
            'employee' => $employee,
            'terminal' => $terminalCount,
            'gate' => $closedGate ? 1 : 0,
            'passes' => $this->closeOpenPasses($employeeId, $cutoff),
            'missing' => $this->missingLabel($terminalCount > 0, $closedGate),
        ];
    }

    private function closeGateIfOpen(Employee $employee, Carbon $windowStart, Carbon $cutoff): bool
    {
        $last = GateLog::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (!$last || $last->direction !== 'in') {
            return false;
        }

        $scannedAt = $last->scanned_at?->copy()->timezone(config('app.timezone'));
        if (!$scannedAt || $scannedAt->lt($windowStart) || $scannedAt->greaterThanOrEqualTo($cutoff)) {
            Log::info('Automatska odjava: ulazak na kapiji je van prozora smjene.', [
                'employee_id' => $employee->id,
                'gate_log_id' => $last->id,
                'scanned_at' => $last->scanned_at?->toDateTimeString(),
            ]);
            return false;
        }

        $rfid = trim((string) ($employee->rfid_code ?? ''));
        GateLog::create([
            'employee_id' => $employee->id,
            'direction' => 'out',
            'scanned_at' => $cutoff,
            'terminal_id' => AttendanceRecord::AUTO_SHIFT_CLOSE_TERMINAL,
            'rfid_code' => $rfid !== '' ? $rfid : null,
        ]);

        return true;
    }

    private function closeOpenPasses(int $employeeId, Carbon $cutoff): int
    {
        $passes = Pass::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'open')
            ->whereNull('end_time')
            ->lockForUpdate()
            ->get();

        foreach ($passes as $pass) {
            $duration = $pass->start_time
                ? (int) max($pass->start_time->diffInMinutes($cutoff, false), 0)
                : 0;

            $pass->update([
                'end_time' => $cutoff,
                'status' => 'closed',
                'duration_minutes' => $duration,
            ]);
        }

        return $passes->count();
    }

    private function missingLabel(bool $terminal, bool $gate): string
    {
        if ($terminal && $gate) {
            return 'Nije se odjavio na terminalu i na kapiji.';
        }

        if ($terminal) {
            return 'Nije se odjavio na terminalu.';
        }

        return 'Nije se odjavio na kapiji.';
    }

    /**
     * @param  list<array{employee: Employee, terminal: int, gate: int, passes: int, missing: string}>  $actions
     */
    private function queueMails(array $actions, string $shiftCode, Carbon $cutoff): int
    {
        foreach ($actions as $action) {
            AutoShiftCloseNotification::query()->create([
                'employee_id' => $action['employee']->id,
                'shift_code' => $shiftCode,
                'cutoff_at' => $cutoff,
                'missing' => $action['missing'],
            ]);
        }

        return count($actions);
    }

    public function sendPendingMails(): int
    {
        $pending = AutoShiftCloseNotification::query()
            ->with('employee')
            ->where('mail_sent', false)
            ->whereNull('mail_skipped_reason')
            ->orderBy('id')
            ->get();

        $sent = 0;
        $groups = $pending->groupBy(function (AutoShiftCloseNotification $notification) {
            $cutoff = $notification->cutoff_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s');

            return $notification->shift_code . '|' . $cutoff;
        });

        foreach ($groups as $group) {
            $sent += $this->dispatchGroup($group);
        }

        return $sent;
    }

    /**
     * @param  Collection<int, AutoShiftCloseNotification>  $notifications
     */
    private function dispatchGroup(Collection $notifications): int
    {
        $shiftCode = (string) $notifications->first()->shift_code;
        $cutoff = $notifications->first()->cutoff_at->copy();
        $byUser = [];

        foreach ($notifications as $notification) {
            $employee = $notification->employee;
            if (!$employee) {
                $notification->update(['mail_skipped_reason' => 'Radnik nije pronađen.']);
                continue;
            }

            $alreadyNotified = collect($notification->notified_user_ids ?? [])
                ->map(fn ($id) => (int) $id);
            $recipients = $this->recipientsFor($employee)
                ->reject(fn (User $user) => $alreadyNotified->contains($user->id))
                ->values();

            if ($recipients->isEmpty() && $alreadyNotified->isEmpty()) {
                $notification->update(['mail_skipped_reason' => $this->skipReason($employee)]);
                Log::info('Automatska odjava upisana bez maila: nema nadležnog sa email adresom.', [
                    'employee_id' => $employee->id,
                    'shift' => $shiftCode,
                    'reason' => $notification->mail_skipped_reason,
                ]);
                continue;
            }

            if ($recipients->isEmpty()) {
                $notification->update(['mail_sent' => true]);
                continue;
            }

            $name = trim((string) $employee->firstName . ' ' . (string) $employee->lastName);
            $row = [
                'name' => $name !== '' ? $name : ('#' . $employee->id),
                'missing' => $notification->missing,
            ];

            foreach ($recipients as $user) {
                $byUser[$user->id]['user'] = $user;
                $byUser[$user->id]['rows'][] = $row;
                $byUser[$user->id]['notification_ids'][] = $notification->id;
            }
        }

        $sent = 0;
        $cutoffLabel = $cutoff->timezone(config('app.timezone'))->format('d.m.Y H:i');

        foreach ($byUser as $bundle) {
            try {
                Mail::to($bundle['user']->email)->send(new AutoShiftCloseMail(
                    $shiftCode,
                    $cutoffLabel,
                    $bundle['rows'],
                ));
                $sent++;
                $this->markNotified($notifications, $bundle['notification_ids'], (int) $bundle['user']->id);
            } catch (\Throwable $e) {
                Log::error('Automatska odjava: mail nije poslan.', [
                    'user_id' => $bundle['user']->id,
                    'shift' => $shiftCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($notifications as $notification) {
            $notification->refresh();
            if ($notification->mail_sent || $notification->mail_skipped_reason || !$notification->employee) {
                continue;
            }

            $recipientIds = $this->recipientsFor($notification->employee)->pluck('id')->map(fn ($id) => (int) $id);
            $notifiedIds = collect($notification->notified_user_ids ?? [])->map(fn ($id) => (int) $id);
            if ($recipientIds->isNotEmpty() && $recipientIds->diff($notifiedIds)->isEmpty()) {
                $notification->update(['mail_sent' => true]);
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, AutoShiftCloseNotification>  $notifications
     * @param  list<int>  $notificationIds
     */
    private function markNotified(Collection $notifications, array $notificationIds, int $userId): void
    {
        foreach ($notificationIds as $id) {
            $notification = $notifications->firstWhere('id', $id);
            if (!$notification) {
                continue;
            }

            $ids = collect($notification->notified_user_ids ?? [])
                ->map(fn ($value) => (int) $value)
                ->push($userId)
                ->unique()
                ->values()
                ->all();

            $notification->notified_user_ids = $ids;
            $notification->save();
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(Employee $employee): Collection
    {
        $ids = collect($employee->nadlezne_osobe ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids->all())
            ->get(['id', 'name', 'email'])
            ->filter(fn (User $user) => trim((string) $user->email) !== '')
            ->values();
    }

    private function skipReason(Employee $employee): string
    {
        $ids = collect($employee->nadlezne_osobe ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        if ($ids->isEmpty()) {
            return 'Nema nadležnog.';
        }

        return 'Nadležni nema email.';
    }

    private function cutoffFor(string $shiftCode, Carbon $now): Carbon
    {
        [$hour, $minute] = match ($shiftCode) {
            'I' => [23, 0],
            'II' => [3, 0],
            'III' => [9, 0],
        };

        $cutoff = $now->copy()->setTime($hour, $minute, 0);
        if ($cutoff->greaterThan($now)) {
            $cutoff->subDay();
        }

        return $cutoff;
    }

    private function windowStart(string $shiftCode, Carbon $cutoff): Carbon
    {
        if ($shiftCode === 'I') {
            return $cutoff->copy()->startOfDay();
        }

        return $cutoff->copy()->subDay();
    }
}

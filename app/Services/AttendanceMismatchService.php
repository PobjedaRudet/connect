<?php

namespace App\Services;

use App\Jobs\SendAttendanceMismatchMail;
use App\Models\AttendanceMismatchNotification;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AttendanceMismatchService
{
    public function __construct(private readonly GateComparisonService $gateComparison)
    {
    }

    /**
     * @param  list<array{from: Carbon, until: Carbon, flag_missing_exit: bool}>  $windows
     * @return Collection<int, AttendanceMismatchNotification>
     */
    public function recordWindows(array $windows): Collection
    {
        $created = collect();

        foreach ($windows as $window) {
            $created = $created->merge($this->recordWindow(
                $window['from'],
                $window['until'],
                (bool) $window['flag_missing_exit'],
            ));
        }

        return $created->values();
    }

    public function sendPendingMails(): int
    {
        $pending = AttendanceMismatchNotification::query()
            ->where('mail_sent', false)
            ->whereNull('mail_skipped_reason')
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $labels = $pending
            ->map(fn (AttendanceMismatchNotification $notification) => $notification->work_date?->format('d.m.Y'))
            ->filter()
            ->unique()
            ->values();

        $periodLabel = match ($labels->count()) {
            0 => Carbon::now(config('app.timezone'))->format('d.m.Y'),
            1 => (string) $labels->first(),
            default => $labels->first() . ' – ' . $labels->last(),
        };

        return $this->dispatchMails($pending, $periodLabel);
    }

    /**
     * @param  Collection<int, AttendanceMismatchNotification>  $notifications
     */
    public function dispatchMails(Collection $notifications, string $periodLabel): int
    {
        if ($notifications->isEmpty()) {
            return 0;
        }

        $notifications->load('employee:id,firstName,lastName,nadlezne_osobe');

        $byUser = [];

        foreach ($notifications as $notification) {
            $recipients = $this->recipientsFor($notification->employee);

            if ($recipients->isEmpty()) {
                $notification->update([
                    'mail_skipped_reason' => $this->skipReason($notification->employee),
                ]);
                Log::info('Neslaganje evidentirano bez maila: nema nadležnog sa email adresom.', [
                    'notification_id' => $notification->id,
                    'employee_id' => $notification->employee_id,
                    'work_date' => $notification->work_date?->toDateString(),
                    'type' => $notification->type,
                ]);
                continue;
            }

            $this->logSupervisorsWithoutEmail($notification, $recipients);

            $notification->update([
                'mail_sent' => true,
                'notified_user_ids' => $recipients->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ]);

            foreach ($recipients as $user) {
                $byUser[$user->id][] = $notification->id;
            }
        }

        foreach ($byUser as $userId => $ids) {
            SendAttendanceMismatchMail::dispatch((int) $userId, $periodLabel, array_values(array_unique($ids)));
        }

        return count($byUser);
    }

    /**
     * @return Collection<int, AttendanceMismatchNotification>
     */
    private function recordWindow(Carbon $from, Carbon $until, bool $flagMissingExit): Collection
    {
        $tz = config('app.timezone');
        $from = $from->copy()->timezone($tz);
        $until = $until->copy()->timezone($tz);
        $date = $from->toDateString();
        $tolerance = GateComparisonService::DEFAULT_TOLERANCE_MINUTES;

        $report = $this->gateComparison->buildDailyReport($date, $tolerance, null, $until);
        $rows = collect($report['rows'])->keyBy(fn (array $row) => (int) $row['employee_id']);

        $created = collect();

        foreach ($rows as $employeeId => $row) {
            $findings = $this->findingsFor($row, $tolerance, $flagMissingExit);

            foreach ($findings as $finding) {
                $notification = AttendanceMismatchNotification::query()->firstOrCreate(
                    [
                        'employee_id' => (int) $employeeId,
                        'work_date' => $date,
                        'type' => $finding['type'],
                    ],
                    [
                        'description' => $finding['description'],
                        'gate_in_at' => $row['gate_in_at'] ?? null,
                        'gate_out_at' => $row['gate_out_at'] ?? null,
                        'building_in_at' => $row['building_in_at'] ?? null,
                        'building_out_at' => $row['building_out_at'] ?? null,
                    ],
                );

                if ($notification->wasRecentlyCreated) {
                    $created->push($notification);
                }
            }
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{type: string, description: string}>
     */
    private function findingsFor(array $row, int $tolerance, bool $flagMissingExit): array
    {
        $findings = [];
        $issues = $row['issues'] ?? [];

        $map = [
            'Nema ulaska na kapiji' => ['gate_missing_in', true],
            'Nema prijave na objektu' => ['no_building_checkin', true],
            'Nema izlaska na kapiji' => ['gate_missing_out', $flagMissingExit],
            'Nema odjave na objektu' => ['no_building_checkout', $flagMissingExit],
        ];

        foreach ($issues as $issue) {
            foreach ($map as $prefix => [$type, $enabled]) {
                if ($enabled && str_starts_with((string) $issue, $prefix)) {
                    $findings[] = ['type' => $type, 'description' => (string) $issue];
                }
            }
        }

        if (($row['entry_diff_minutes'] ?? null) !== null && abs((int) $row['entry_diff_minutes']) > $tolerance) {
            $findings[] = [
                'type' => 'gate_entry_diff',
                'description' => 'Razlika ulaska veća od ' . $tolerance . ' min (' . (int) $row['entry_diff_minutes'] . ' min).',
            ];
        }

        if ($flagMissingExit && ($row['exit_diff_minutes'] ?? null) !== null && abs((int) $row['exit_diff_minutes']) > $tolerance) {
            $findings[] = [
                'type' => 'gate_exit_diff',
                'description' => 'Razlika izlaska veća od ' . $tolerance . ' min (' . (int) $row['exit_diff_minutes'] . ' min).',
            ];
        }

        return $findings;
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function logSupervisorsWithoutEmail(AttendanceMismatchNotification $notification, Collection $recipients): void
    {
        $ids = collect($notification->employee?->nadlezne_osobe ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique();

        $missing = $ids->diff($recipients->pluck('id')->map(fn ($id) => (int) $id));
        if ($missing->isEmpty()) {
            return;
        }

        Log::info('Neslaganje: dio nadležnih nema email, mail je poslan ostalima.', [
            'notification_id' => $notification->id,
            'employee_id' => $notification->employee_id,
            'user_ids' => $missing->values()->all(),
        ]);
    }

    private function skipReason(?Employee $employee): string
    {
        $ids = collect($employee?->nadlezne_osobe ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        if ($ids->isEmpty()) {
            return 'Nema nadležnog.';
        }

        return 'Nadležni nema email.';
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(?Employee $employee): Collection
    {
        $ids = collect($employee?->nadlezne_osobe ?? [])
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

}

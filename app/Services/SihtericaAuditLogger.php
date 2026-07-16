<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\SihtericaAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SihtericaAuditLogger
{
    public function logCreated(?User $user, AttendanceRecord $record, ?Request $request = null): void
    {
        $this->write($user, 'created', $record, null, $this->snapshot($record), $request);
    }

    public function logUpdated(?User $user, AttendanceRecord $record, array $before, ?Request $request = null): void
    {
        $after = $this->snapshot($record->fresh() ?? $record);
        $this->write($user, 'updated', $record, $before, $after, $request);
    }

    public function logDeleted(?User $user, AttendanceRecord $record, array $before, ?Request $request = null): void
    {
        $this->write($user, 'deleted', $record, $before, null, $request);
    }

    public function snapshot(AttendanceRecord $record): array
    {
        $tz = config('app.timezone');

        return [
            'id' => (int) $record->id,
            'employee_id' => (int) $record->employee_id,
            'shift_id' => $record->shift_id ? (int) $record->shift_id : null,
            'entry_time' => $record->entry_time
                ? Carbon::parse($record->entry_time)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
            'exit_time' => $record->exit_time
                ? Carbon::parse($record->exit_time)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
            'effective_start' => $record->effective_start
                ? Carbon::parse($record->effective_start)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
            'duration_minutes' => $record->duration_minutes,
            'status' => $record->status,
            'late_flag' => $record->late_flag,
            'terminal_in' => $record->terminal_in,
            'terminal_out' => $record->terminal_out,
        ];
    }

    private function write(
        ?User $user,
        string $action,
        AttendanceRecord $record,
        ?array $before,
        ?array $after,
        ?Request $request,
    ): void {
        $tz = config('app.timezone');
        $workDate = null;

        $sourceTime = $after['entry_time'] ?? $before['entry_time'] ?? null;
        if ($sourceTime) {
            try {
                $workDate = Carbon::parse($sourceTime, $tz)->toDateString();
            } catch (\Throwable $e) {
                $workDate = null;
            }
        }

        SihtericaAuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'attendance_record_id' => $record->id,
            'employee_id' => $record->employee_id,
            'work_date' => $workDate,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $this->truncateUserAgent($request?->userAgent()),
        ]);
    }

    private function truncateUserAgent(?string $ua): ?string
    {
        if ($ua === null || $ua === '') {
            return null;
        }

        return mb_substr($ua, 0, 512);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    private const I_SHIFT_BASE_MINUTES = 480;
    private const SHIFT_END_OVERTIME_GRACE_MINUTES = 30;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'entry_time',
        'effective_start',
        'exit_time',
        'duration_minutes',
        'status', // working | left
        'late_flag', // minor | major | null
        'terminal_in',
        'terminal_out',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'effective_start' => 'datetime',
        'exit_time' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            // Recalculate when times change.
            if (!$record->isDirty('exit_time') && !$record->isDirty('entry_time') && !$record->isDirty('effective_start')) {
                return;
            }

            $start = $record->effective_start ?: $record->entry_time;
            $end = $record->exit_time;

            if (!$start || !$end) {
                $record->duration_minutes = null;
                return;
            }

            $minutes = $start->diffInMinutes($end, false);

            $workedMinutes = max($minutes, 0);

            if (self::isIShift($record) && $workedMinutes > self::I_SHIFT_BASE_MINUTES) {
                $record->duration_minutes = self::I_SHIFT_BASE_MINUTES;
                return;
            }

            $record->duration_minutes = $workedMinutes;
        });

        static::saved(function (self $record): void {
            self::syncOvertime($record);
        });
    }

    private static function isIShift(self $record): bool
    {
        if (!$record->shift_id) {
            return false;
        }

        $shift = $record->relationLoaded('shift') ? $record->shift : $record->shift()->first();
        if (!$shift) {
            return false;
        }

        $code = $shift->attendance_credit_code ?? null;
        if (!is_string($code)) {
            return false;
        }
        return strtoupper(trim($code)) === 'I';
    }

    private static function syncOvertime(self $record): void
    {
        // Overtime is based on staying beyond scheduled shift end.
        // If shift/end_time can't be resolved, keep no overtime.

        $start = $record->effective_start ?: $record->entry_time;
        $end = $record->exit_time;

        if (!$start || !$end) {
            AttendanceOvertime::query()->where('attendance_record_id', $record->id)->delete();
            return;
        }

        $shiftEnd = self::resolveShiftEndMoment($record, Carbon::parse($start));
        if (!$shiftEnd) {
            AttendanceOvertime::query()->where('attendance_record_id', $record->id)->delete();
            return;
        }

        $tz = config('app.timezone');
        $exit = Carbon::parse($end)->timezone($tz);

        $graceEnd = $shiftEnd->copy()->addMinutes(self::SHIFT_END_OVERTIME_GRACE_MINUTES);
        if ($exit->lessThanOrEqualTo($graceEnd)) {
            AttendanceOvertime::query()->where('attendance_record_id', $record->id)->delete();
            return;
        }

        // Once overtime is triggered, count all minutes beyond scheduled shift end.
        $overtimeMinutes = max($shiftEnd->diffInMinutes($exit, false), 0);
        $workDate = Carbon::parse($start)->timezone($tz)->toDateString();

        AttendanceOvertime::updateOrCreate(
            ['attendance_record_id' => $record->id],
            [
                'employee_id' => $record->employee_id,
                'work_date' => $workDate,
                'overtime_minutes' => $overtimeMinutes,
            ]
        );
    }

    private static function resolveShiftEndMoment(self $record, Carbon $workStart): ?Carbon
    {
        if (!$record->shift_id) {
            return null;
        }

        $shift = $record->relationLoaded('shift') ? $record->shift : $record->shift()->first();
        if (!$shift || !$shift->start_time || !$shift->end_time) {
            return null;
        }

        $tz = config('app.timezone');
        $workDate = Carbon::parse($workStart)->timezone($tz)->toDateString();

        $startTimeStr = $shift->start_time instanceof Carbon
            ? $shift->start_time->format('H:i:s')
            : (string) $shift->start_time;

        $endTimeStr = $shift->end_time instanceof Carbon
            ? $shift->end_time->format('H:i:s')
            : (string) $shift->end_time;

        try {
            $shiftStart = Carbon::parse($workDate . ' ' . $startTimeStr, $tz);
            $shiftEnd = Carbon::parse($workDate . ' ' . $endTimeStr, $tz);
        } catch (\Throwable $e) {
            return null;
        }

        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        return $shiftEnd;
    }

    /**
     * Serialize dates in local app timezone instead of UTC ISO-8601.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        $tz = config('app.timezone');
        return Carbon::parse($date)->setTimezone($tz)->format('Y-m-d H:i:s');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}

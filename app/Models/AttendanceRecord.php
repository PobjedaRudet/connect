<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'entry_time',
        'effective_start',
        'exit_time',
        'status', // working | left
        'late_flag', // minor | major | null
        'terminal_id',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'effective_start' => 'datetime',
        'exit_time' => 'datetime',
    ];

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

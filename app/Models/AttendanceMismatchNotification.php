<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMismatchNotification extends Model
{
    protected $fillable = [
        'employee_id',
        'work_date',
        'type',
        'description',
        'gate_in_at',
        'gate_out_at',
        'building_in_at',
        'building_out_at',
        'pass_start_at',
        'pass_end_at',
        'mail_sent',
        'notified_user_ids',
        'mail_skipped_reason',
    ];

    protected $casts = [
        'work_date' => 'date',
        'gate_in_at' => 'datetime',
        'gate_out_at' => 'datetime',
        'building_in_at' => 'datetime',
        'building_out_at' => 'datetime',
        'pass_start_at' => 'datetime',
        'pass_end_at' => 'datetime',
        'mail_sent' => 'boolean',
        'notified_user_ids' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

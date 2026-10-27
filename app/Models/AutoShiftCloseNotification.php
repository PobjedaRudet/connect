<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoShiftCloseNotification extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_code',
        'cutoff_at',
        'missing',
        'mail_sent',
        'notified_user_ids',
        'mail_skipped_reason',
    ];

    protected $casts = [
        'cutoff_at' => 'datetime',
        'mail_sent' => 'boolean',
        'notified_user_ids' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

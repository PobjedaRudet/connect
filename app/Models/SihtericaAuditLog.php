<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SihtericaAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'attendance_record_id',
        'employee_id',
        'work_date',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'work_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

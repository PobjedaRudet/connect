<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pass extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type', // privatni | službeni
        'reason',
        'start_time',
        'end_time',
        'approved_by', // employee_id of approver
        'approved_by_user_id',
        'status', // open | closed
        'duration_minutes', // total minutes outside when closed
        'approved',
        'late_pass',       // true when auto-created from a late check-in
        'late_minutes',    // actual minutes the employee was late
        'early_departure', // true when auto-created from an early check-out
        'early_minutes',   // actual minutes the employee left early
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'approved' => 'boolean',
        'late_pass' => 'boolean',
        'late_minutes' => 'integer',
        'early_departure' => 'boolean',
        'early_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}

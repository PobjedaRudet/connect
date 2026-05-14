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
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'approved' => 'boolean',
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

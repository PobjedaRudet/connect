<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualLeaveUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'annual_leave_decision_id',
        'leave_id',
        'date_from',
        'date_to',
        'days',
        'note',
        'created_by_user_id',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'days' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(AnnualLeaveDecision::class, 'annual_leave_decision_id');
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class);
    }
}

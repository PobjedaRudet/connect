<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualLeaveDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'year',
        'part',
        'decision_number',
        'decision_date',
        'valid_from',
        'valid_to',
        'granted_days',
        'carried_over_days',
        'note',
        'attachment_path',
        'created_by_user_id',
        'approved_by_user_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'part' => 'string',
        'decision_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'granted_days' => 'decimal:2',
        'carried_over_days' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(AnnualLeaveUsage::class);
    }

    public function totalGrantedDays(): float
    {
        return (float) $this->granted_days + (float) $this->carried_over_days;
    }

    public function usedDays(): float
    {
        return (float) $this->usages()->sum('days');
    }

    public function remainingDays(): float
    {
        return $this->totalGrantedDays() - $this->usedDays();
    }
}

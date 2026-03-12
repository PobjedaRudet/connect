<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'usage_date',
        'minutes_used',
        'usage_type',
        'note',
        'created_by_user_id',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'minutes_used' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OvertimeUsageAllocation::class);
    }
}

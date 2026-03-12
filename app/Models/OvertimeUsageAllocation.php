<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeUsageAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'overtime_usage_id',
        'attendance_overtime_id',
        'minutes_allocated',
    ];

    protected $casts = [
        'minutes_allocated' => 'integer',
    ];

    public function usage(): BelongsTo
    {
        return $this->belongsTo(OvertimeUsage::class, 'overtime_usage_id');
    }

    public function overtime(): BelongsTo
    {
        return $this->belongsTo(AttendanceOvertime::class, 'attendance_overtime_id');
    }
}

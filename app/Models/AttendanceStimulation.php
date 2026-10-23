<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceStimulation extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'employee_id',
        'work_date',
        'minutes',
    ];

    protected $casts = [
        'work_date' => 'date',
        'minutes' => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

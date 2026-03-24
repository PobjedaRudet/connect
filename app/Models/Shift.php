<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'start_time',
        'end_time',
        'attendance_credit_code',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}

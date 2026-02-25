<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SickLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_id',
        'from',
        'to',
        'days',
        'document_number',
        'document_date',
        'doctor',
        'diagnosis_code',
        'note',
        'attachment_path',
        'status',
        'created_by_user_id',
        'approved_by_user_id',
    ];

    protected $casts = [
        'from' => 'date',
        'to' => 'date',
        'days' => 'integer',
        'document_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class);
    }
}

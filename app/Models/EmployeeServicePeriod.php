<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeServicePeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employer_name',
        'position',
        'service_type',
        'start_date',
        'end_date',
        'is_recognized',
        'document_number',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_recognized' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

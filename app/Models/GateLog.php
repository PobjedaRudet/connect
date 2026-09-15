<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencija ulaza/izlaza sa kapije (okretna vrata na ulazu u firmu).
 * Odvojeno od AttendanceRecord — ne utiče na obračun radnog vremena, smjena ni prekovremenih.
 */
class GateLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'direction', // in | out
        'scanned_at',
        'terminal_id',
        'rfid_code',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadniciPoRedosljedu extends Model
{
    use HasFactory;

    protected $table = 'radnici_po_redosljedu';

    protected $fillable = [
        'prezime',
        'ime',
        'radno_mjesto',
        'redni_broj',
        'employee_id',
    ];

    protected $casts = [
        'redni_broj' => 'float',
    ];
}

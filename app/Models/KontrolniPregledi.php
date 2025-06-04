<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KontrolniPregledi extends Model
{
    use HasFactory;

    protected $table = 'kontrolni_pregledi';

    protected $fillable = [
        'pregledi_id',
        'employee_id',
        'datum_kontrolnog_pregleda',
        'kontrolni_komentar',
        'status',
    ];
}

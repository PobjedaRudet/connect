<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadnoMjesto extends Model
{
    protected $table = 'radna_mjesta';

    protected $fillable = [
        'sifra',
        'radno_mjesto',
        'strucna_sprema',
        'smjer',
        'broj',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'radno_mjesto', 'radno_mjesto');
    }
}

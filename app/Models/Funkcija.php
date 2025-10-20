<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funkcija extends Model
{
    protected $table = 'funkcije';
    protected $primaryKey = 'Funkcija';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['Funkcija', 'Opis', 'Redosljed', 'is_absent'];
}

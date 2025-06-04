<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pregledi extends Model
{
    //
    protected $fillable = [
    // ...ostala polja...
    'employee_id','datum_pregleda','type', 'kontrolni_pregled','komentar', 'organizacija'
];
  public function employee()
{
    return $this->belongsTo(Employee::class, 'employee_id', 'empID');
}
public function kontrolniPregledi()
{
    return $this->hasMany(\App\Models\KontrolniPregledi::class, 'pregledi_id');
}
}

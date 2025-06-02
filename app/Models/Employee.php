<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    //
    protected $fillable = ['name', 'position', 'department', 'period'];
    protected $table = 'employees';

    public function pregledi()
    {
        return $this->hasMany(Pregledi::class, 'employee_id', 'empID');
    }
}

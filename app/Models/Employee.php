<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    //
    protected $fillable = ['name', 'position', 'department', 'period', 'rfid_code'];
    protected $table = 'employees';


    public function pregledi()
    {
        // Veza: pregledis.employee_id <-> employees.empID
        return $this->hasMany(Pregledi::class, 'employee_id', 'empID');
    }
}

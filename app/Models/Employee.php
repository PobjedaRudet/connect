<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    //
    protected $fillable = ['name', 'position', 'department', 'period', 'rfid_code'];
    protected $table = 'employees';

    protected $casts = [
        'nadlezne_osobe' => 'array',
    ];


    public function pregledi()
    {
        // Veza: pregledis.employee_id <-> employees.empID
        return $this->hasMany(Pregledi::class, 'employee_id', 'empID');
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'employee_shift');
    }
}

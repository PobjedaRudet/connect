<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    //
    protected $fillable = [
        'empID',
        'rfid_code',
        'nadlezne_osobe',
        'lastName',
        'firstName',
        'middleName',
        'period',
        'rizik',
        'radno_mjesto',
        'sex',
        'jobTitle',
        'dept',
        'email',
        'startDate',
        'status',
        'termDate',
        'termReason',
        'homeStreet',
        'homeZip',
        'homeCity',
        'homeCounty',
        'homeCountr',
        'homeState',
        'birthDate',
        'brthCountr',
        'martStatus',
        'nChildren',
        'govID',
        'picture',
        'position',
        'Active',
        'profesionalno_oboljenje',
        'invalidnost_radnika',
    ];
    protected $table = 'employees';

    protected $casts = [
        'nadlezne_osobe' => 'array',
        'rizik' => 'boolean',
        'Active' => 'boolean',
        'period' => 'integer',
        'nChildren' => 'integer',
        'startDate' => 'date',
        'termDate' => 'date',
        'birthDate' => 'date',
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

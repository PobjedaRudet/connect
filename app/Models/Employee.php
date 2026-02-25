<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function annualLeaveDecisions(): HasMany
    {
        return $this->hasMany(AnnualLeaveDecision::class);
    }

    public function annualLeaveUsages(): HasMany
    {
        return $this->hasMany(AnnualLeaveUsage::class);
    }

    public function servicePeriods(): HasMany
    {
        return $this->hasMany(EmployeeServicePeriod::class);
    }

    public function sickLeaves(): HasMany
    {
        return $this->hasMany(SickLeave::class);
    }
}

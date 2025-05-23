<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    //
    protected $fillable = ['employee_id', 'date','in', 'out', 'status'];
    protected $table = 'attendances';
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    public function leave()
    {
        return $this->hasMany(Leave::class);
    }
    public function scopeFilter($query, array $filters)
    {
        if ($filters['search'] ?? false) {
            $query->where('name', 'like', '%' . request('search') . '%');
        }
        if ($filters['date'] ?? false) {
            $query->whereDate('date', request('date'));
        }
        if ($filters['status'] ?? false) {
            $query->where('status', request('status'));
        }
    }
}

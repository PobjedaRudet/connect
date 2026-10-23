<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'vikend_redovan',
    ];

    protected $casts = [
        'vikend_redovan' => 'boolean',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'dept', 'id');
    }
}

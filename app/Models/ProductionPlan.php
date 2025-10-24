<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'objekat',
        'laboracija_datum',
        'delivery_date',
        'planned_by',
    ];

    public function items()
    {
        return $this->hasMany(ProductionPlanItem::class);
    }

    public function planner()
    {
        return $this->belongsTo(User::class, 'planned_by');
    }
}

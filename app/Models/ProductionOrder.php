<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $table = 'production_orders';

    protected $fillable = [
        'OrderNumber',
        'OrderDate',
        'Description',
        'Status',
        'BojaDuzinaProvodnika',
        'Pakovanje',
        'Tip',
        'AtestPaketa',
        'CeOznaka',
        'KlasaOpasnosti',
        'UNBroj',
        'VrstaProvodnika',
        'Metraza',
        'RokIsporuke',
        'DatumPredaje',
        'DatumPrijema',
        'token',
        'Napomena',
        'dodatno',
        'parent_nalog_id',
        'user_id',
        'partner_id',
        'is_void',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'is_void' => 'boolean',
        'voided_at' => 'datetime',
        'user_id' => 'integer',
        'partner_id' => 'integer',
    ];

    // Relacije
    public function approvals()
    {
        return $this->hasMany(Approval::class, 'order_id');
    }

    public function details()
    {
        return $this->hasMany(ProductionOrderDetail::class, 'production_order_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

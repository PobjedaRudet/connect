<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $table = 'approvals';
    protected $fillable = [
        'order_id',
        'UserId',
        'Funkcija',
        'Odobreno',
        'DatumOdobravanja',
        'Komentar',
        'signed_by_proxy',
    ];

    protected $casts = [
        'Odobreno' => 'boolean',
        'DatumOdobravanja' => 'datetime',
        'signed_by_proxy' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(ProductionOrder::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'UserId');
    }

    public function funkcija()
    {
        return $this->belongsTo(Funkcija::class, 'Funkcija', 'Funkcija');
    }
}

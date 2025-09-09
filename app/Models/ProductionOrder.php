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
        'parent_nalog_id',
        'user_id',
        'partner_id',
    ];

    // Dodaj relacije po potrebi
}

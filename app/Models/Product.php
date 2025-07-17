<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'Naziv',
        'SkraceniNaziv',
        'JedinicaMjere',
        'Code',
        'NetExplosiveQuantity',
        'UoM_meter',
        'UoM_cubicmeter',
        'GrossWeight',
        'UsporenjeMs',
        'UNNumber',
        'HazardClass',
        'CEMarkNumber',
        'NumeraProizvoda',
        'RokTrajanja',
        'ProductCode',
        'VrstaProvodnika',
        'TypeOfProduct',
        'ADRnaziv',
    ];
}

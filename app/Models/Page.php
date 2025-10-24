<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['name','route_name','description'];

    public function funkcije()
    {
        // Pivot uses string key 'funkcija' to reference Funkcija primary key
        return $this->belongsToMany(Funkcija::class, 'funkcija_page', 'page_id', 'funkcija', 'id', 'Funkcija');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Funkcija;
use Illuminate\Database\Seeder;

class FunkcijeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Funkcija' => 'Radnik', 'Opis' => 'Radnik', 'Redosljed' => 1],
            ['Funkcija' => 'Šef Komercijale', 'Opis' => 'Šef Komercijale', 'Redosljed' => 2],
            ['Funkcija' => 'Direktor Komercijale', 'Opis' => 'Direktor Komercijale', 'Redosljed' => 3],
            ['Funkcija' => 'Direktor Proizvodnje', 'Opis' => 'Direktor Proizvodnje', 'Redosljed' => 4],
            ['Funkcija' => 'Šef Operative', 'Opis' => 'Šef Operative', 'Redosljed' => 5],

            // Production substitutes (not part of the main approval chain; used when Director of Production is absent)
            ['Funkcija' => 'Zamjenik1', 'Opis' => 'Zamjenik1', 'Redosljed' => 90],
            ['Funkcija' => 'Zamjenik2', 'Opis' => 'Zamjenik2', 'Redosljed' => 91],
        ];

        foreach ($rows as $row) {
            Funkcija::updateOrCreate(['Funkcija' => $row['Funkcija']], $row);
        }
    }
}

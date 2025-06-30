<?php

namespace App\Http\Controllers;

use App\Models\KontrolniPregledi;
use App\Models\Pregledi;
use Illuminate\Http\Request;

class KontrolniPreglediController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'pregledi_id' => 'required|integer|exists:pregledis,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'datum_kontrolnog_pregleda' => 'required|date',
            'kontrolni_komentar' => 'nullable|string',
            'status' => 'required|boolean',
            // Ako je status true, kreirati novi kontrolni pregled, ako je false, ažurirati postojeći
            // Ako nema dodatnog kontrolnog pregleda, treba u tabeli pregledis unjeti kontrolni_pregled = 2, zato što to prestavlja da je pregled završen
            // 1 za kontrolni pregled, 0 za završeni  redovni pregled bez potrebe za kontrolnim pregledom


        ]);
        //Treba ažurirati i tabelu pregledis i ažurirati kolonu kontrolni_pregled
        $pregled = Pregledi::findOrFail($data['pregledi_id']);
        if ($data['status']) {
            // Ako je status true, kreiramo novi kontrolni pregled
            $pregled->kontrolni_pregled = 1; // Postavljamo kontrolni_pregled na 1
        } else {
            // Ako je status false, ažuriramo postojeći kontrolni pregled
            $pregled->kontrolni_pregled = 2; // Postavljamo kontrolni_pregled na 2 (završeni)
        }

        $pregled->save(); // Spremamo promjene u tabeli pregledis

        $kontrolni = KontrolniPregledi::create($data);
        // Postavljamo status na 0 za kontrolne preglede, jer taj pregled je završen, a ako je u tabeli pregledis kontrolni_pregled = 1, to znači da treba zakazati novi kontrolni pregled
        $kontrolni->status = 0; // Postavljamo status na 0 za kontrolne preglede

        return response()->json(['success' => true, 'kontrolni' => $kontrolni]);
    }

    public function kontrolniPregledi()
    {
        // Dohvati sve preglede gdje je kontrolni_pregled = 1, 0 je ako , zajedno sa zaposlenikom
        $pregledi = Pregledi::where('kontrolni_pregled', 1)
            ->with('employee')
            ->orderByDesc('datum_pregleda')
            ->get();
        return response()->json($pregledi);
    }
}

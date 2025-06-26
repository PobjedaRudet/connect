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
            'status' => 'required|boolean', //Ako je status true, kreirati novi kontrolni pregled, ako je false, ažurirati postojeći
        ]);

        $kontrolni = KontrolniPregledi::create($data);
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

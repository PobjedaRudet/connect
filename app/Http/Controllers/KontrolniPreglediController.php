<?php

namespace App\Http\Controllers;

use App\Models\KontrolniPregledi;
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
        ]);

        $kontrolni = KontrolniPregledi::create($data);
        return response()->json(['success' => true, 'kontrolni' => $kontrolni]);
    }
}

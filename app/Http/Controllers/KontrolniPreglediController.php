<?php

namespace App\Http\Controllers;

use App\Models\KontrolniPregledi;
use App\Models\Pregledi;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KontrolniPreglediController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'pregledi_id' => 'required|integer|exists:pregledis,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'datum_kontrolnog_pregleda' => 'required|date',
            'kontrolni_komentar' => 'nullable|string',
            'status' => 'required|in:0,1,true,false,"0","1"',
        ]);

        $needsFollowUp = in_array((string) $data['status'], ['1', 'true'], true);

        $pregled = Pregledi::findOrFail($data['pregledi_id']);
        $pregled->kontrolni_pregled = $needsFollowUp ? 1 : 2;
        $pregled->save();

        $kontrolni = KontrolniPregledi::create([
            'pregledi_id' => $data['pregledi_id'],
            'employee_id' => $data['employee_id'],
            'datum_kontrolnog_pregleda' => $data['datum_kontrolnog_pregleda'],
            'kontrolni_komentar' => $data['kontrolni_komentar'] ?? null,
            'status' => 0,
        ]);

        return response()->json(['success' => true, 'kontrolni' => $kontrolni]);
    }

    public function update(Request $request, int $id)
    {
        $kontrolni = KontrolniPregledi::findOrFail($id);

        $data = $request->validate([
            'kontrolni_komentar' => 'nullable|string',
            'status' => 'required|in:0,1,true,false,"0","1"',
        ]);

        $kontrolni->update([
            'kontrolni_komentar' => $data['kontrolni_komentar'] ?? null,
            'status' => in_array((string) $data['status'], ['1', 'true'], true) ? 1 : 0,
        ]);

        return response()->json(['success' => true, 'kontrolni' => $kontrolni]);
    }

    public function kontrolniPregledi()
    {
        $pregledi = Pregledi::where('kontrolni_pregled', 1)
            ->whereHas('employee', function ($query) {
                $query->where('Active', '1');
            })
            ->with('employee')
            ->orderByDesc('datum_pregleda')
            ->get();

        return response()->json($pregledi);
    }
}

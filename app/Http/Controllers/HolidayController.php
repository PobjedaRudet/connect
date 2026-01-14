<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    protected function ensureDirectorProizvodnje()
    {
        $user = Auth::user();
        $allowed = ['Direktor Proizvodnje', 'Zamjenik1', 'Zamjenik2'];
        if (!$user || !in_array(($user->funkcija ?? null), $allowed, true)) {
            abort(403, 'Nedozvoljen pristup');
        }
    }

    public function index()
    {
        $this->ensureDirectorProizvodnje();
        $holidays = Holiday::orderBy('date')->get(['id','date','name']);
        return Inertia::render('Planiranje/Praznici', [
            'holidays' => $holidays,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $data = $request->validate([
            'date' => ['required','date', Rule::unique('holidays','date')],
            'name' => ['required','string','max:255'],
        ]);
        $holiday = Holiday::create($data);
        // Return the new holiday as a shared prop for Inertia
        return redirect()->back()->with([
            'success' => 'Praznik dodat.',
            'holiday' => $holiday,
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        $this->ensureDirectorProizvodnje();
        $holiday->delete();
        return redirect()->back()->with('success', 'Praznik obrisan.');
    }
}

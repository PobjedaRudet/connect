<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Pregledi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PreglediController extends Controller
{
    public function index()
    {
        $radnici = Employee::get()->take(10);
        return Inertia::render('Pregledi/Index', [
            'radnici' => $radnici,
        ]);
    }

    public function store(Request $request, Employee $radnik)
    {
        $request->validate([
            'datum_pregleda' => 'required|date',
            'komentar' => 'nullable|string'
        ]);

        $radnik->pregledi()->create($request->only('datum_pregleda', 'komentar'));

        return redirect()->route('pregledi.index', $radnik->id);
    }

    public function reportUpcoming()
    {
        $today = Carbon::today();
        $nextWeek = $today->copy()->addDays(30);

        $employees = Employee::whereHas('pregledi') // samo oni koji imaju barem jedan pregled
            ->with(['pregledi' => function ($query) {
                $query->orderByDesc('datum_pregleda')->limit(10);
            }])
            ->get();


        $upcoming = [];
        $expired = [];

        //Log::info('Uposlenici', $employees->toArray());
        foreach ($employees as $employee) {
            $lastExam = $employee->pregledi->first();
            if (!$lastExam || !$employee->period) continue;

            $nextDue = Carbon::parse($lastExam->datum_pregleda)->addMonths((int)$employee->period);

            /*         Log::info('Processing employee', ['id' => $employee->id, 'name' => $employee->firstName . ' ' . $employee->lastName, 'radno_mjesto' => $employee->radno_mjesto]);
            Log::info('Last exam date', ['date' => $lastExam->datum_pregleda]);
            Log::info('Next due date', ['next_due' => $nextDue]);


            Log::info('Employee ID', ['id' => $employee->id]);
            Log::info('Last exam', ['date' => $lastExam->datum_pregleda]);
            Log::info('Period', ['months' => $employee->period]);
            Log::info('Next due', ['next_due' => $nextDue]); */

            if ($nextDue->between($today, $nextWeek)) {
                $upcoming[] = [
                    'employee' => $employee,
                    'next_due' => $nextDue,
                ];
            } elseif ($nextDue->lessThan($today)) {
                $expired[] = [
                    'employee' => $employee,
                    'next_due' => $nextDue,
                ];
            }
        }
        // Log::info("Nadolazeci", $upcoming);
        return Inertia::render('Pregledi/ZaSedamDana', [
            'upcoming' => $upcoming,
            'expired' => $expired
        ]);
    }

    public function azuriraj(Request $request)
    {
        Log::info('Azuriranje pregleda', ['request' => $request->all()]);
        $data = $request->validate([
            'ids' => 'required|array',
            'datum' => 'required|date',
            'tip' => 'required|string',
            'kontrolni' => 'required',
            'komentar' => 'nullable|string',
            'ustanova' => 'required|string',
        ]);

        foreach ($data['ids'] as $id) {
            Pregledi::create([
                'employee_id' => $id,
                'datum_pregleda' => $data['datum'],
                'type' => $data['tip'],
                'kontrolni_pregled' => $data['kontrolni'],
                'komentar' => $data['komentar'],
                'organizacija' => $data['ustanova'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function preglediNakonAzuriranja()
    {
        // --- Dodaj ovo za povrat novih podataka ---
        $today = \Carbon\Carbon::today();
        $nextWeek = $today->copy()->addDays(30);

        $employees = \App\Models\Employee::whereHas('pregledi')
            ->with(['pregledi' => function ($query) {
                $query->orderByDesc('datum_pregleda')->limit(10);
            }])
            ->get();

        $upcoming = [];
        $expired = [];

        foreach ($employees as $employee) {
            $lastExam = $employee->pregledi->first();
            if (!$lastExam || !$employee->period) continue;

            $nextDue = \Carbon\Carbon::parse($lastExam->datum_pregleda)->addMonths((int)$employee->period);

            if ($nextDue->between($today, $nextWeek)) {
                $upcoming[] = [
                    'employee' => $employee,
                    'next_due' => $nextDue,
                ];
            } elseif ($nextDue->lessThan($today)) {
                $expired[] = [
                    'employee' => $employee,
                    'next_due' => $nextDue,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'upcoming' => $upcoming,
            'expired' => $expired,
        ]);
    }

    public function kontrolniPregledi()
    {
        // Dohvati sve preglede gdje je kontrolni_pregled = 1, zajedno sa zaposlenikom
        $pregledi = Pregledi::where('kontrolni_pregled', 1)
            ->with('employee')
            ->orderByDesc('datum_pregleda')
            ->get();
        return response()->json($pregledi);
    }
}

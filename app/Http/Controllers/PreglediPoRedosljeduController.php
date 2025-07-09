<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RadniciPoRedosljedu;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class PreglediPoRedosljeduController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $nextWeek = $today->copy()->addDays(30);

        // Dohvati redosljed iz tabele radnici_po_redosljedu
        $redosljed = RadniciPoRedosljedu::all()->keyBy('employee_id');

        $employees = Employee::whereHas('pregledi')
            ->with(['pregledi' => function ($query) {
                $query->orderByDesc('datum_pregleda');
            }])
            ->get();

        $upcoming = [];
        $expired = [];

        foreach ($employees as $employee) {
            $lastExam = $employee->pregledi->first();
            if (!$lastExam || !$employee->period) continue;

            $nextDue = Carbon::parse($lastExam->datum_pregleda)->addMonths((int)$employee->period);
            $redni_broj = $redosljed[$employee->empID]->redni_broj ?? 99999;
            $radno_mjesto = $redosljed[$employee->empID]->radno_mjesto ?? $employee->radno_mjesto;

            $item = [
                'employee' => $employee,
                'next_due' => $nextDue,
                'redni_broj' => $redni_broj,
                'radno_mjesto' => $radno_mjesto,
            ];

            if ($nextDue->between($today, $nextWeek)) {
                $upcoming[] = $item;
            } elseif ($nextDue->lessThan($today)) {
                $expired[] = $item;
            }
        }

        // Sortiraj po redni_broj
        usort($upcoming, fn($a, $b) => ($a['redni_broj'] ?? 99999) <=> ($b['redni_broj'] ?? 99999));
        usort($expired, fn($a, $b) => ($a['redni_broj'] ?? 99999) <=> ($b['redni_broj'] ?? 99999));

        return Inertia::render('Pregledi/ZaSedamDana', [
            'upcoming' => $upcoming,
            'expired' => $expired
        ]);
    }
}

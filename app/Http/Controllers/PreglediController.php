<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Pregledi;
use App\Models\RadniciPoRedosljedu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PreglediController extends Controller
{
    public function index()
    {
        $radnici = Employee::with(['pregledi' => function ($q) {
            $q->orderByDesc('datum_pregleda');
        }])->get();
        // Dodaj lastExamDate svakom radniku
        $radnici = $radnici->map(function ($radnik) {
            $radnikArr = $radnik->toArray();
            $lastExam = $radnik->pregledi->first();
            $radnikArr['lastExamDate'] = $lastExam ? $lastExam->datum_pregleda : null;
            return $radnikArr;
        });
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
            ->where('status', '1')
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
        /* return Inertia::render('Pregledi/ZaSedamDana', [
            'upcoming' => $upcoming,
            'expired' => $expired
        ]); */
        // Radnici koji NEMAJU NIJEDAN pregled
        // SVI radnici, sa ili bez pregleda
        $radnici = Employee::with(['pregledi' => function ($q) {
            $q->orderByDesc('datum_pregleda');
        }])->get();
    $bezPregleda = $radnici->filter(function($radnik) {
        return $radnik->pregledi->isEmpty();
    })->values()->all(); // pretvori u array
    Log::info("Bez pregleda");
    Log::info("Bez pregleda", $bezPregleda);

    return Inertia::render('Pregledi/ZaSedamDana', [
        'upcoming' => $upcoming,
        'expired' => $expired,
        'bezPregleda' => $bezPregleda, // šaljemo i njih
    ]);
    }

    public function zaSedamDana()
    {
        $today = \Carbon\Carbon::today();
        $nextWeek = $today->copy()->addDays(30);

        // SVI radnici, sa ili bez pregleda
        $radnici = Employee::with(['pregledi' => function ($q) {
            $q->orderByDesc('datum_pregleda');
        }])->get();

        $upcoming = [];
        $expired = [];

        foreach ($radnici as $employee) {
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

        // Radnici koji NEMAJU NIJEDAN pregled
    $bezPregleda = $radnici->filter(function($radnik) {
        return $radnik->pregledi->isEmpty();
    })->values()->all(); // pretvori u array
    Log::info("Bez pregleda");
    Log::info("Bez pregleda", $bezPregleda);

    return Inertia::render('Pregledi/ZaSedamDana', [
        'upcoming' => $upcoming,
        'expired' => $expired,
        'bezPregleda' => $bezPregleda, // šaljemo i njih
    ]);
    }

    public function reportUpcomingNextMonth()
    {
        $today = Carbon::today();
        $startNextMonth = $today->copy()->addMonthNoOverflow()->startOfMonth();
        $endNextMonth = $startNextMonth->copy()->endOfMonth();

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

            // Upcoming samo za naredni mjesec
            if ($nextDue->between($startNextMonth, $endNextMonth)) {
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
        return Inertia::render('Pregledi/PreglediZaIduciMjesec', [
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

    /**
     * API: Izvještaj svih ljekarskih pregleda sa traženim kolonama za PPZ
     */
    public function apiIzvjestajPregledi()
    {
        $pregledi = Pregledi::with(['employee'])
            ->whereHas('employee', function ($query) {
                $query->where('status', '1');
            })
            ->orderByDesc('datum_pregleda')
            ->get()
            ->map(function ($p) {
                return [
                    'organizacija' => $p->organizacija,
                    'datum_pregleda' => $p->datum_pregleda,
                    'lastName' => $p->employee->lastName ?? '',
                    'middleName' => $p->employee->middleName ?? '',
                    'firstName' => $p->employee->firstName ?? '',
                    'radno_mjesto' => $p->employee->radno_mjesto ?? '',
                    'type' => $p->type,
                    'profesionalno_oboljenje' => $p->employee->profesionalno_oboljenje ?? '',
                    'invalidnost_radnika' => $p->employee->invalidnost_radnika ?? '',
                    'employee_id' => $p->employee->empID ?? null,
                ];
            });
        return response()->json($pregledi);
    }

    /**
     * API: Export pregleda u Word (.docx)
     */
    public function apiIzvjestajPreglediWord(Request $request)
    {
        try {
            $data = $request->all();
            // Koristi TemplateProcessor i postojeći template
            $templatePath = storage_path('app/template.docx');
            if (!file_exists($templatePath)) {
                Log::error('Word template nije pronađen: ' . $templatePath);
                return response()->json(['error' => 'Word template nije pronađen.'], 500);
            }
            $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

            // Kreiraj tabelu u novom PhpWord objektu
            $phpWordTable = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWordTable->addSection(['orientation' => 'landscape']);
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 50]);
            $headerCellStyle = ['bgColor' => 'D9D9D9'];
            $table->addRow();
            $table->addCell(500, $headerCellStyle)->addText('#', ['bold' => true]);
            $table->addCell(2000, $headerCellStyle)->addText('Organizacija', ['bold' => true]);
            $table->addCell(1500, $headerCellStyle)->addText('Datum pregleda', ['bold' => true]);
            $table->addCell(2500, $headerCellStyle)->addText('Prezime (Srednje ime) Ime', ['bold' => true]);
            $table->addCell(2000, $headerCellStyle)->addText('Radno mjesto', ['bold' => true]);
            $table->addCell(1500, $headerCellStyle)->addText('Sposobnost', ['bold' => true]);
            $table->addCell(2000, $headerCellStyle)->addText('Profesionalno oboljenje', ['bold' => true]);
            $table->addCell(1500, $headerCellStyle)->addText('Invalidnost', ['bold' => true]);
            foreach ($data as $idx => $p) {
                $table->addRow();
                $table->addCell(500)->addText($idx + 1);
                $table->addCell(2000)->addText($p['organizacija'] ?? '');
                $table->addCell(1500)->addText(isset($p['datum_pregleda']) ? date('d.m.Y', strtotime($p['datum_pregleda'])) : '');
                $ime = ($p['lastName'] ?? '') . (isset($p['middleName']) && $p['middleName'] ? ' (' . $p['middleName'] . ')' : '') . ' ' . ($p['firstName'] ?? '');
                $table->addCell(2500)->addText(trim($ime));
                $table->addCell(2000)->addText($p['radno_mjesto'] ?? '');
                $table->addCell(1500)->addText($p['type'] ?? '');
                $table->addCell(2000)->addText($p['profesionalno_oboljenje'] ?? '');
                $table->addCell(1500)->addText($p['invalidnost_radnika'] ?? '');
            }
            // Ubaci tabelu u template
            $templateProcessor->setComplexBlock('TABLE', $table);
            $tempFile = tempnam(sys_get_temp_dir(), 'pregledi') . '.docx';
            $templateProcessor->saveAs($tempFile);
            return response()->download($tempFile, 'izvjestaj_pregledi.docx')->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Greška pri exportu Word izvještaja: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return response()->json(['error' => 'Greška pri exportu Word izvještaja: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Svi radnici po redosljedu
     */
    public function apiRadniciPoRedosljedu()
    {
        return response()->json(RadniciPoRedosljedu::orderBy('redni_broj', 'asc')->get());
    }

    // API: Izmjena pregleda
    public function update(Request $request, $id)
    {
        Log::info('UPDATE PREGLED request', $request->all());
        $pregled = Pregledi::findOrFail($id);
        try {
            $data = $request->validate([
                'datum_pregleda' => 'required|date',
                'type' => 'required|string',
                'komentar' => 'nullable|string',
                'organizacija' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('UPDATE PREGLED validation error', $e->errors());
            throw $e;
        }
        $pregled->update($data);
        return response()->json(['success' => true, 'pregled' => $pregled]);
    }

    // API: Brisanje pregleda
    public function destroy($id)
    {
        $pregled = Pregledi::findOrFail($id);
        $pregled->delete();
        return response()->json(['success' => true]);
    }
}

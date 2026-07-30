<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Pass;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AutoPassApprovalPagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    /**
     * Lista automatski kreiranih izlaznica (kašnjenje / prijevremeni odlazak)
     * koje čekaju odobrenje nadređenog. Rezerva ako email promakne.
     */
    public function index(Request $request): Response
    {
        $tz = config('app.timezone');

        // Ako kolone ne postoje, prikaži praznu listu (bez pada).
        if (!Schema::hasColumn('passes', 'late_pass') && !Schema::hasColumn('passes', 'early_departure')) {
            return Inertia::render('HR/AutoIzlazniceOdobravanje', [
                'pending'  => [],
                'approved' => [],
            ]);
        }

        $employees = $this->scopedEmployeeQuery($request->user())
            ->get(['id', 'empID', 'firstName', 'lastName'])
            ->keyBy('id');

        $employeeIds = $employees->keys();

        $select = ['id', 'employee_id', 'type', 'reason', 'start_time', 'end_time',
                   'duration_minutes', 'status', 'approved'];
        foreach (['late_pass', 'late_minutes', 'early_departure', 'early_minutes'] as $col) {
            if (Schema::hasColumn('passes', $col)) {
                $select[] = $col;
            }
        }

        $autoPassFilter = function ($query) {
            $query->where(function ($q) {
                if (Schema::hasColumn('passes', 'late_pass')) {
                    $q->orWhere('late_pass', true);
                }
                if (Schema::hasColumn('passes', 'early_departure')) {
                    $q->orWhere('early_departure', true);
                }
            });
        };

        // Neodobrene — čekaju odluku
        $pending = Pass::query()
            ->when($employeeIds->isNotEmpty(), fn ($q) => $q->whereIn('employee_id', $employeeIds->all()),
                                               fn ($q) => $q->whereRaw('1 = 0'))
            ->where('approved', false)
            ->tap($autoPassFilter)
            ->orderByDesc('start_time')
            ->get($select)
            ->map(fn (Pass $p) => $this->mapPass($p, $employees, $tz))
            ->values();

        // Odobrene u zadnjih 30 dana — istorija
        $approved = Pass::query()
            ->when($employeeIds->isNotEmpty(), fn ($q) => $q->whereIn('employee_id', $employeeIds->all()),
                                               fn ($q) => $q->whereRaw('1 = 0'))
            ->where('approved', true)
            ->where('start_time', '>=', Carbon::now($tz)->subDays(30)->startOfDay())
            ->tap($autoPassFilter)
            ->orderByDesc('start_time')
            ->limit(100)
            ->get($select)
            ->map(fn (Pass $p) => $this->mapPass($p, $employees, $tz))
            ->values();

        return Inertia::render('HR/AutoIzlazniceOdobravanje', [
            'pending'  => $pending,
            'approved' => $approved,
        ]);
    }

    /**
     * Nadređeni ručno odabere tip (privatni/službeni) i odobri izlaznicu.
     */
    public function approve(Request $request, Pass $pass): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:privatni,službeni'],
        ]);

        // Mora biti automatski kreirana izlaznica
        $isAuto = (bool) ($pass->late_pass ?? false) || (bool) ($pass->early_departure ?? false);
        if (!$isAuto) {
            return back()->withErrors(['type' => 'Ova izlaznica nije automatski kreirana.']);
        }

        // Autorizacija — nadređeni mora imati pristup radniku
        if (!$this->canAccessEmployee($request->user(), (int) $pass->employee_id)) {
            return back()->withErrors(['type' => 'Nemate ovlaštenje za ovu izlaznicu.']);
        }

        if ($pass->approved) {
            return back()->banner('Izlaznica #' . $pass->id . ' je već odobrena.');
        }

        $pass->update([
            'type'     => $validated['type'],
            'approved' => true,
        ]);

        $label = $validated['type'] === 'privatni' ? 'privatna' : 'službena';

        return back()->banner('Izlaznica #' . $pass->id . ' odobrena kao ' . $label . '.');
    }

    private function mapPass(Pass $pass, $employees, string $tz): array
    {
        $emp = $employees->get((int) $pass->employee_id);

        return [
            'id'               => $pass->id,
            'employee_id'      => (int) $pass->employee_id,
            'empID'            => (int) ($emp?->empID ?? 0),
            'full_name'        => trim(($emp?->lastName ?? '') . ' ' . ($emp?->firstName ?? '')),
            'type'             => $pass->type,
            'reason'           => $pass->reason,
            'start_time'       => optional($pass->start_time)->timezone($tz)->format('Y-m-d H:i:s'),
            'end_time'         => optional($pass->end_time)?->timezone($tz)->format('Y-m-d H:i:s'),
            'duration_minutes' => $pass->duration_minutes !== null ? (int) $pass->duration_minutes : null,
            'kind'             => ($pass->early_departure ?? false) ? 'early' : 'late',
            'late_minutes'     => isset($pass->late_minutes) ? (int) $pass->late_minutes : null,
            'early_minutes'    => isset($pass->early_minutes) ? (int) $pass->early_minutes : null,
            'approved'         => (bool) $pass->approved,
        ];
    }
}

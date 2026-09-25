<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HR\Concerns\ScopesEmployeesByUser;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\GateLog;
use App\Models\Pass;
use App\Services\GateComparisonService;
use App\Services\SihtericaAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KapijaController extends Controller
{
    use ScopesEmployeesByUser;

    public function index()
    {
        return Inertia::render('Kapija');
    }

    public function data()
    {
        $today = Carbon::today();

        // Statistics
        $totalEmployees = Employee::where(function ($q) {
            $q->whereNull('Active')->orWhere('Active', 1);
        })->count();

        $currentlyWorking = AttendanceRecord::where('status', 'working')
            ->whereDate('entry_time', $today)
            ->count();

        $checkedOutToday = AttendanceRecord::where('status', 'left')
            ->whereDate('entry_time', $today)
            ->count();

        $totalCheckedInToday = $currentlyWorking + $checkedOutToday;

        $activePasses = Pass::where('status', 'open')
            ->whereDate('start_time', $today)
            ->count();

        // Recent 10 check-ins/check-outs
        $recentRecords = AttendanceRecord::with('employee:id,firstName,lastName')
            ->where('entry_time', '>=', $today)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'        => $r->id,
                'employee'  => $r->employee ? $r->employee->firstName . ' ' . $r->employee->lastName : '—',
                'type'      => $r->exit_time ? 'odjava' : 'prijava',
                'time'      => $r->exit_time
                    ? $r->exit_time->format('H:i')
                    : ($r->entry_time ? $r->entry_time->format('H:i') : '—'),
                'timestamp' => $r->updated_at->toISOString(),
            ]);

        // Today's passes
        $passes = Pass::with('employee:id,firstName,lastName')
            ->whereDate('start_time', $today)
            ->orderByDesc('start_time')
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'employee'   => $p->employee ? $p->employee->firstName . ' ' . $p->employee->lastName : '—',
                'type'       => $p->type,
                'start_time' => $p->start_time->format('H:i'),
                'end_time'   => $p->end_time ? $p->end_time->format('H:i') : null,
                'status'     => $p->status,
            ]);

        return response()->json([
            'stats' => [
                'totalEmployees'     => $totalEmployees,
                'currentlyWorking'   => $currentlyWorking,
                'checkedOutToday'    => $checkedOutToday,
                'totalCheckedInToday'=> $totalCheckedInToday,
                'activePasses'       => $activePasses,
                'percentage'         => $totalEmployees > 0
                    ? round(($currentlyWorking / $totalEmployees) * 100, 1)
                    : 0,
            ],
            'recentRecords' => $recentRecords,
            'passes'        => $passes,
        ]);
    }

    /**
     * Dnevni izvjestaj poredjenja kapije (okretna vrata na ulazu) naspram
     * terminala kod objekta -- za rucnu kontrolu da niko ne provlaci karticu
     * za drugog radnika. Vidi GateComparisonService.
     */
    public function poredjenje(Request $request, GateComparisonService $service)
    {
        $date = (string) ($request->query('date') ?: Carbon::now(config('app.timezone'))->toDateString());
        $tolerance = (int) ($request->query('tolerance') ?: GateComparisonService::DEFAULT_TOLERANCE_MINUTES);

        $report = $service->buildDailyReport($date, $tolerance);

        return Inertia::render('Kapija/Poredjenje', $report);
    }

    /**
     * Isti izvještaj kao poredjenje(), ali samo za radnike nadređenog
     * (admin / Šef HR vide sve). Vidi ScopesEmployeesByUser.
     */
    public function poredjenjeHr(Request $request, GateComparisonService $service)
    {
        $date = (string) ($request->query('date') ?: Carbon::now(config('app.timezone'))->toDateString());
        $tolerance = (int) ($request->query('tolerance') ?: GateComparisonService::DEFAULT_TOLERANCE_MINUTES);

        $employeeIds = $this->visibleEmployeeQuery($request->user())->pluck('id');
        $report = $service->buildDailyReport($date, $tolerance, $employeeIds);
        $editableEmployeeIds = $this->editableEmployeeIds($request->user());
        $report['rows'] = collect($report['rows'])->map(function (array $row) use ($editableEmployeeIds) {
            $row['can_edit'] = $editableEmployeeIds === null
                || in_array((int) $row['employee_id'], $editableEmployeeIds, true);

            return $row;
        })->all();

        return Inertia::render('Kapija/Poredjenje', array_merge($report, [
            'from_hr' => true,
        ]));
    }

    public function storeComparison(Request $request, SihtericaAuditLogger $audit)
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:gate,building'],
            'event' => ['required', 'in:in,out'],
            'employee_id' => ['required', 'integer'],
            'attendance_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'tolerance' => ['nullable', 'integer', 'min:0'],
        ]);

        $employeeId = (int) $validated['employee_id'];
        if (! $this->canAccessEmployee($request->user(), $employeeId)) {
            abort(403, 'Nemate pristup odabranom radniku.');
        }

        $tz = config('app.timezone');
        $moment = Carbon::parse($validated['date'].' '.$validated['time'], $tz);

        if ($validated['kind'] === 'gate') {
            $rfid = trim((string) Employee::query()->whereKey($employeeId)->value('rfid_code'));
            GateLog::create([
                'employee_id' => $employeeId,
                'direction' => $validated['event'],
                'scanned_at' => $moment,
                'terminal_id' => 'MANUAL',
                'rfid_code' => $rfid !== '' ? $rfid : null,
            ]);
        } else {
            $record = null;
            if (! empty($validated['attendance_id'])) {
                $record = AttendanceRecord::query()
                    ->whereKey($validated['attendance_id'])
                    ->where('employee_id', $employeeId)
                    ->first();
            }

            if ($record) {
                $before = $audit->snapshot($record);
                if ($validated['event'] === 'in') {
                    $record->update([
                        'entry_time' => $moment,
                        'effective_start' => $moment,
                        'terminal_in' => 'MANUAL',
                    ]);
                } else {
                    $entry = $record->entry_time ? Carbon::parse($record->entry_time)->timezone($tz) : null;
                    if ($entry && $moment->lessThanOrEqualTo($entry)) {
                        $moment->addDay();
                    }
                    $record->update([
                        'exit_time' => $moment,
                        'status' => 'left',
                        'terminal_out' => 'MANUAL',
                    ]);
                }
                $audit->logUpdated($request->user(), $record->fresh(), $before, $request);
            } else {
                $record = AttendanceRecord::create([
                    'employee_id' => $employeeId,
                    'entry_time' => $validated['event'] === 'in' ? $moment : null,
                    'effective_start' => $validated['event'] === 'in' ? $moment : null,
                    'exit_time' => $validated['event'] === 'out' ? $moment : null,
                    'status' => $validated['event'] === 'out' ? 'left' : 'working',
                    'terminal_in' => $validated['event'] === 'in' ? 'MANUAL' : null,
                    'terminal_out' => $validated['event'] === 'out' ? 'MANUAL' : null,
                ]);
                $audit->logCreated($request->user(), $record, $request);
            }
        }

        return $this->redirectToComparison($validated['date'], $validated['tolerance'] ?? null);
    }

    public function updateComparison(Request $request, SihtericaAuditLogger $audit)
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:gate,building'],
            'event' => ['required', 'in:in,out'],
            'id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'tolerance' => ['nullable', 'integer', 'min:0'],
        ]);

        $tz = config('app.timezone');
        $moment = Carbon::parse($validated['date'].' '.$validated['time'], $tz);

        if ($validated['kind'] === 'gate') {
            $log = GateLog::query()->findOrFail($validated['id']);
            if (! $this->canAccessEmployee($request->user(), (int) $log->employee_id)) {
                abort(403, 'Nemate pristup odabranom radniku.');
            }
            if ($log->direction !== $validated['event']) {
                return back()->withErrors(['time' => 'Zapis ne odgovara odabranom ulazu ili izlazu.']);
            }

            $log->update(['scanned_at' => $moment]);
        } else {
            $record = AttendanceRecord::query()->findOrFail($validated['id']);
            if (! $this->canAccessEmployee($request->user(), (int) $record->employee_id)) {
                abort(403, 'Nemate pristup odabranom radniku.');
            }

            $before = $audit->snapshot($record);
            if ($validated['event'] === 'in') {
                $record->update([
                    'entry_time' => $moment,
                    'effective_start' => $moment,
                    'terminal_in' => $this->markTerminalEdited($record->terminal_in),
                ]);
            } else {
                $entry = $record->entry_time ? Carbon::parse($record->entry_time)->timezone($tz) : null;
                if ($entry && $moment->lessThanOrEqualTo($entry)) {
                    $moment->addDay();
                }
                $record->update([
                    'exit_time' => $moment,
                    'status' => 'left',
                    'terminal_out' => $this->markTerminalEdited($record->terminal_out),
                ]);
            }
            $audit->logUpdated($request->user(), $record->fresh(), $before, $request);
        }

        return $this->redirectToComparison($validated['date'], $validated['tolerance'] ?? null);
    }

    public function deleteComparison(Request $request, SihtericaAuditLogger $audit)
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:gate,building'],
            'event' => ['required', 'in:in,out'],
            'id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'tolerance' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validated['kind'] === 'gate') {
            $log = GateLog::query()->findOrFail($validated['id']);
            if (! $this->canAccessEmployee($request->user(), (int) $log->employee_id)) {
                abort(403, 'Nemate pristup odabranom radniku.');
            }
            if ($log->direction !== $validated['event']) {
                return back()->withErrors(['id' => 'Zapis ne odgovara odabranom ulazu ili izlazu.']);
            }
            $log->delete();
        } else {
            $record = AttendanceRecord::query()->findOrFail($validated['id']);
            if (! $this->canAccessEmployee($request->user(), (int) $record->employee_id)) {
                abort(403, 'Nemate pristup odabranom radniku.');
            }

            $before = $audit->snapshot($record);
            if ($validated['event'] === 'out') {
                $record->update([
                    'exit_time' => null,
                    'status' => 'working',
                    'terminal_out' => null,
                ]);
                $audit->logUpdated($request->user(), $record->fresh(), $before, $request);
            } else {
                $audit->logDeleted($request->user(), $record, $before, $request);
                $record->delete();
            }
        }

        return $this->redirectToComparison($validated['date'], $validated['tolerance'] ?? null);
    }

    private function redirectToComparison(string $date, mixed $tolerance)
    {
        return redirect()->route('hr.poredjenje', [
            'date' => $date,
            'tolerance' => $tolerance ?? GateComparisonService::DEFAULT_TOLERANCE_MINUTES,
        ]);
    }

    private function markTerminalEdited(?string $terminal): string
    {
        $terminal = trim((string) $terminal);

        if ($terminal === '' || $terminal === 'MANUAL') {
            return 'MANUAL';
        }

        if (str_starts_with($terminal, 'EDITED:')) {
            return $terminal;
        }

        return 'EDITED:'.$terminal;
    }
}

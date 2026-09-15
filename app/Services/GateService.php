<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\GateLog;
use Carbon\Carbon;

/**
 * Logika za terminal na kapiji (okretna vrata na ulazu u firmu).
 *
 * Namjerno jednostavnije od AttendanceService: kapija samo bilježi ulaz/izlaz
 * (naizmjenično, jedan čitač) radi kontrole — ne dodjeljuje smjene, ne računa
 * radno vrijeme/prekovremene i ne zna za izlaznice (Pass). To je posao terminala
 * kod objekta. Kapija služi da se kasnije uporedi vrijeme ulaska/izlaska sa
 * onim što je zabilježeno na terminalu kod objekta (vidi GateComparisonService).
 */
class GateService
{
    public function processScan(string $rfid, ?string $terminalId = null): array
    {
        $employee = Employee::where('rfid_code', $rfid)
            ->select(['id', 'firstName', 'lastName'])
            ->first();

        if (!$employee) {
            return ['status' => 'error', 'message' => 'Nepoznata kartica!'];
        }

        $lastLog = GateLog::where('employee_id', $employee->id)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->first();

        $direction = (!$lastLog || $lastLog->direction === 'out') ? 'in' : 'out';
        $now = Carbon::now();

        $log = GateLog::create([
            'employee_id' => $employee->id,
            'direction' => $direction,
            'scanned_at' => $now,
            'terminal_id' => $terminalId,
            'rfid_code' => $rfid,
        ]);

        $fullName = trim((string) $employee->firstName . ' ' . (string) $employee->lastName);

        return [
            'status' => $direction === 'in' ? 'gate-in' : 'gate-out',
            'message' => $direction === 'in'
                ? "Ulaz registrovan — {$fullName}"
                : "Izlaz registrovan — {$fullName}",
            'employee_id' => $employee->id,
            'employee_full_name' => $fullName,
            'direction' => $direction,
            'scanned_at' => $log->scanned_at->toDateTimeString(),
        ];
    }
}

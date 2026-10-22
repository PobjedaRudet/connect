<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\GateLog;
use App\Models\Pass;
use Carbon\Carbon;

/**
 * Logika za terminal na kapiji (okretna vrata na ulazu u firmu).
 *
 * Kapija bilježi ulaz/izlaz (naizmjenično, jedan čitač). Ne dodjeljuje smjene
 * i ne računa radno vrijeme. Izlaz je dozvoljen samo ako je radnik odjavljen
 * na terminalu objekta, ili ako ima otvorenu izlaznicu.
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
        $fullName = trim((string) $employee->firstName . ' ' . (string) $employee->lastName);

        if ($direction === 'out' && $this->missingBuildingCheckout($employee->id)) {
            return [
                'status' => 'error',
                'message' => 'Ne možete izaći na kapiji dok se ne odjavite na terminalu.',
                'employee_id' => $employee->id,
                'employee_full_name' => $fullName,
            ];
        }

        $now = Carbon::now();

        $log = GateLog::create([
            'employee_id' => $employee->id,
            'direction' => $direction,
            'scanned_at' => $now,
            'terminal_id' => $terminalId,
            'rfid_code' => $rfid,
        ]);

        return [
            'status' => $direction === 'in' ? 'gate-in' : 'gate-out',
            'message' => $direction === 'in'
                ? "Ulaz registrovan"
                : "Izlaz registrovan",
            'employee_id' => $employee->id,
            'employee_full_name' => $fullName,
            'direction' => $direction,
            'scanned_at' => $log->scanned_at->toDateTimeString(),
        ];
    }

    /**
     * Radnik je još prijavljen na terminalu objekta i nema otvorenu izlaznicu.
     */
    private function missingBuildingCheckout(int $employeeId): bool
    {
        $stillWorking = AttendanceRecord::where('employee_id', $employeeId)
            ->whereNull('exit_time')
            ->exists();

        if (!$stillWorking) {
            return false;
        }

        $openPass = Pass::where('employee_id', $employeeId)
            ->where('status', 'open')
            ->whereNull('end_time')
            ->exists();

        return !$openPass;
    }
}

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
        $employee = $this->findEmployeeByRfid($rfid);

        if (!$employee) {
            return ['status' => 'error', 'message' => 'Nepoznata kartica!'];
        }

        $direction = $this->resolveDirection($employee->id);
        $fullName = $this->employeeFullName($employee);

        if ($direction === 'out' && $this->missingBuildingCheckout($employee->id)) {
            return [
                'status' => 'error',
                'message' => 'Ne možete izaći na kapiji dok se ne odjavite na terminalu.',
                'employee_id' => $employee->id,
                'employee_full_name' => $fullName,
            ];
        }

        $log = $this->createGateLog($employee->id, $rfid, $direction, Carbon::now(), $terminalId);

        return $this->scanPayload($direction, $fullName, $employee->id, $log);
    }

    /**
     * Sinhronizacija skena koji je terminal spremio dok nije imao vezu.
     * scanned_at je vrijeme sa terminala. http_status skida kontroler prije odgovora.
     */
    public function processOfflineScan(string $rfid, ?string $terminalId, string $timestamp): array
    {
        $moment = $this->parseScanTimestamp($timestamp);
        if (!$moment) {
            return $this->withHttpStatus([
                'status' => 'error',
                'message' => 'Neispravan format vremena: '.$timestamp,
            ], 422);
        }

        if ($moment->greaterThan(Carbon::now()->addMinutes(5))) {
            return $this->withHttpStatus([
                'status' => 'error',
                'message' => 'Vrijeme skena je u budućnosti.',
            ], 422);
        }

        $employee = $this->findEmployeeByRfid($rfid);
        if (!$employee) {
            return $this->withHttpStatus([
                'status' => 'error',
                'message' => 'Nepoznata kartica!',
            ], 404);
        }

        $fullName = $this->employeeFullName($employee);
        $existing = $this->findExistingScan($employee->id, $terminalId, $moment);
        if ($existing) {
            return $this->withHttpStatus(
                $this->scanPayload($existing->direction, $fullName, $employee->id, $existing, true),
                200,
            );
        }

        $direction = $this->resolveDirection($employee->id, $moment);
        $log = $this->createGateLog($employee->id, $rfid, $direction, $moment, $terminalId, true);

        return $this->withHttpStatus(
            $this->scanPayload($direction, $fullName, $employee->id, $log, true),
            201,
        );
    }

    private function findEmployeeByRfid(string $rfid): ?Employee
    {
        return Employee::where('rfid_code', $rfid)
            ->select(['id', 'firstName', 'lastName'])
            ->first();
    }

    /**
     * Bez $asOf: zadnji zapis ukupno (online sken).
     * Sa $asOf: zadnji zapis sa scanned_at <= vrijeme skena, uključujući
     * automatske out zapise. Kasniji zapisi se ne mijenjaju.
     */
    private function resolveDirection(int $employeeId, ?Carbon $asOf = null): string
    {
        $query = GateLog::where('employee_id', $employeeId);
        if ($asOf !== null) {
            $query->where('scanned_at', '<=', $asOf);
        }

        $lastLog = $query
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->first();

        return (!$lastLog || $lastLog->direction === 'out') ? 'in' : 'out';
    }

    private function findExistingScan(int $employeeId, ?string $terminalId, Carbon $moment): ?GateLog
    {
        $from = $moment->format('Y-m-d H:i:s');
        $to = $moment->copy()->startOfSecond()->addSecond()->format('Y-m-d H:i:s');

        $query = GateLog::query()
            ->where('employee_id', $employeeId)
            ->where('scanned_at', '>=', $from)
            ->where('scanned_at', '<', $to);

        if ($terminalId === null) {
            $query->whereNull('terminal_id');
        } else {
            $query->where('terminal_id', $terminalId);
        }

        return $query->orderByDesc('id')->first();
    }

    private function createGateLog(
        int $employeeId,
        string $rfid,
        string $direction,
        Carbon $scannedAt,
        ?string $terminalId,
        bool $offline = false,
    ): GateLog {
        $attributes = [
            'employee_id' => $employeeId,
            'direction' => $direction,
            'scanned_at' => $scannedAt,
            'terminal_id' => $terminalId,
            'rfid_code' => $rfid,
        ];

        if ($offline) {
            $attributes['offline'] = true;
        }

        return GateLog::create($attributes);
    }

    private function scanPayload(
        string $direction,
        string $fullName,
        int $employeeId,
        GateLog $log,
        bool $offline = false,
    ): array {
        $message = $direction === 'in' ? 'Ulaz registrovan' : 'Izlaz registrovan';
        if ($offline) {
            $message .= ' (offline)';
        }

        return [
            'status' => $direction === 'in' ? 'gate-in' : 'gate-out',
            'message' => $message,
            'employee_id' => $employeeId,
            'employee_full_name' => $fullName,
            'direction' => $direction,
            'scanned_at' => $log->scanned_at->toDateTimeString(),
        ];
    }

    private function employeeFullName(Employee $employee): string
    {
        return trim((string) $employee->firstName . ' ' . (string) $employee->lastName);
    }

    private function parseScanTimestamp(string $timestamp): ?Carbon
    {
        $timezone = config('app.timezone');

        foreach (['Y-m-d H:i:s.u', 'Y-m-d H:i:s'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $timestamp, $timezone);
            } catch (\Throwable) {
                continue;
            }

            $errors = Carbon::getLastErrors();
            $warningCount = is_array($errors) ? (int) ($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int) ($errors['error_count'] ?? 0) : 0;

            if ($parsed instanceof Carbon && $errorCount === 0 && $warningCount === 0) {
                return $parsed;
            }
        }

        return null;
    }

    private function withHttpStatus(array $payload, int $status): array
    {
        $payload['http_status'] = $status;

        return $payload;
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

<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Glavna metoda – skeniranje RFID kartice.
     */
    public function processScan(string $rfid, ?string $terminalId = null): array
    {
        // Pass prefix handling: P... => privatni, S... => službeni
        $passType = null;
        $rfidTrimmed = $rfid;
        if (preg_match('/^[Pp]/', $rfid)) {
            $passType = 'privatni';
            $rfidTrimmed = substr($rfid, 1);
        } elseif (preg_match('/^[Ss]/', $rfid)) {
            $passType = 'službeni';
            $rfidTrimmed = substr($rfid, 1);
        }

        $employee = Employee::where('rfid_code', $rfidTrimmed)->first();

        if (!$employee) {
            return ['status' => 'error', 'message' => 'Nepoznata kartica!'];
        }

        $openRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNull('exit_time')
            ->first();

        if ($openRecord) {
            // If a pass prefix while working: toggle pass (issue or close)
            if ($passType) {
                return $this->togglePass($employee, $openRecord, $passType);
            }
            // No prefix: if there's an active (open) pass, close it automatically instead of checkout
            $activePass = \App\Models\Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
            if ($activePass) {
                $activePass->update([
                    'end_time' => Carbon::now(),
                    'status' => 'closed',
                ]);
                return [
                    'status' => 'pass-closed',
                    'message' => $activePass->type === 'privatni' ? 'Privatna izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                    'pass' => $activePass,
                ];
            }
            // No active pass: proceed with checkout
            return $this->checkout($employee, $openRecord, $terminalId);
        }

        // If no open record, pass cannot be issued; treat as check-in
        if ($passType) {
            return ['status' => 'error', 'message' => 'Radnik nije prijavljen; izlaznica se može izdati samo tokom rada.'];
        }
        return $this->checkin($employee, $terminalId);
    }

    /**
     * Offline scan: uses provided timestamp and terminal_id.
     * Mirrors online scan logic (checkin / checkout / pass toggle), but uses provided timestamp
     * instead of current time. Pass issuance rules remain: cannot issue if not checked in.
     */
    public function processOfflineScan(string $rfid, string $terminalId, string $timestamp): array
    {
        try {
            $moment = Carbon::parse($timestamp);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Neispravan format vremena: '.$timestamp];
        }

        // Prefix handling identical to online scan
        $passType = null;
        $rfidTrimmed = $rfid;
        if (preg_match('/^[Pp]/', $rfid)) {
            $passType = 'privatni';
            $rfidTrimmed = substr($rfid, 1);
        } elseif (preg_match('/^[Ss]/', $rfid)) {
            $passType = 'službeni';
            $rfidTrimmed = substr($rfid, 1);
        }

        $employee = Employee::where('rfid_code', $rfidTrimmed)->first();
        if (!$employee) {
            return ['status' => 'error', 'message' => 'Nepoznata kartica!'];
        }

        // Find open attendance record
        $openRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNull('exit_time')
            ->first();

        if ($openRecord) {
            if ($passType) {
                // Toggle pass with provided timestamp
                return $this->togglePassAtTime($employee, $openRecord, $passType, $moment);
            }
            // Auto-close pass if one is active
            $activePass = \App\Models\Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
            if ($activePass) {
                $activePass->update([
                    'end_time' => $moment,
                    'status' => 'closed',
                ]);
                return [
                    'status' => 'pass-closed',
                    'message' => $activePass->type === 'privatni' ? 'Privatna izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                    'pass' => $activePass,
                ];
            }
            // Checkout with provided timestamp
            $openRecord->update([
                'exit_time' => $moment,
                'status' => 'left',
                'terminal_id' => $openRecord->terminal_id ?? $terminalId,
            ]);
            return [
                'status' => 'checkout',
                'message' => 'Radnik odjavljen (offline)',
                'record' => $openRecord,
            ];
        }

        // No open record: cannot issue pass, must be plain check-in
        if ($passType) {
            return ['status' => 'error', 'message' => 'Radnik nije prijavljen; izlaznica (offline) se može izdati samo tokom rada.'];
        }

        // Check-in using provided timestamp
        $shift = $this->findClosestShift($employee, $moment);
        $effectiveStart = null;
        $lateFlag = 'none';
        if ($shift) {
            $shiftStart = $this->buildShiftStartForDate($shift->start_time, $moment);
            [$effectiveStart, $lateFlag] = $this->adjustStartTime($shiftStart, $moment);
        }
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'entry_time' => $moment,
            'effective_start' => $effectiveStart,
            'status' => 'working',
            'late_flag' => $lateFlag === 'none' ? null : $lateFlag,
            'terminal_id' => $terminalId,
        ]);
        return [
            'status' => 'checkin',
            'message' => 'Radnik prijavljen (offline)',
            'record' => $record,
            'late_flag' => $lateFlag,
        ];
    }

    /**
     * Prijava radnika.
     */
    private function checkin(Employee $employee, ?string $terminalId = null): array
    {
        $entryTime = Carbon::now();

        $shift = $this->findClosestShift($employee, $entryTime);

        $effectiveStart = null;
        $lateFlag = 'none';
        if ($shift) {
            $shiftStart = Carbon::today()->setTimeFromTimeString($shift->start_time);
            [$effectiveStart, $lateFlag] = $this->adjustStartTime($shiftStart, $entryTime);
        }

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'entry_time' => $entryTime,
            'effective_start' => $effectiveStart,
            'status' => 'working',
            'late_flag' => $lateFlag === 'none' ? null : $lateFlag,
            'terminal_id' => $terminalId,
        ]);

        return [
            'status' => 'checkin',
            'message' => 'Radnik prijavljen',
            'record' => $record,
            'late_flag' => $lateFlag,
        ];
    }

    /**
     * Odjava radnika.
     */
    private function checkout(Employee $employee, AttendanceRecord $record, ?string $terminalId = null): array
    {
        $exitTime = Carbon::now();

        $record->update([
            'exit_time' => $exitTime,
            'status' => 'left',
        ]);

        return [
            'status' => 'checkout',
            'message' => 'Radnik odjavljen',
            'record' => $record,
        ];
    }

    /**
     * Pronalazi najbližu smjenu radniku.
     */
    private function findClosestShift(Employee $employee, Carbon $entryTime)
    {
        // Biraj smjene isključivo iz employee departmenta
        $department = $employee->department;
        if (!$department) {
            return null;
        }
        $shifts = $department->shifts;

        $closestShift = null;
        $minFutureDiff = null; // prefer upcoming shift on same day
        $minPastDiff = null;   // fallback: closest past shift on same day

        foreach ($shifts as $shift) {
            $shiftStart = Carbon::today()->setTimeFromTimeString($shift->start_time);
            $minutesToShift = $entryTime->diffInMinutes($shiftStart, false); // negative if past

            // If entry falls within this shift window (start..end), choose it directly
            if (!empty($shift->end_time)) {
                $shiftEnd = Carbon::today()->setTimeFromTimeString($shift->end_time);
                if ($entryTime->betweenIncluded($shiftStart, $shiftEnd)) {
                    $closestShift = $shift;
                    break;
                }
            } else {
                // No end_time defined: if entry is after start but before next shift start (handled below), favor this as past
                if ($minutesToShift < 0 && $minFutureDiff === null) {
                    $absPast = abs($minutesToShift);
                    if ($minPastDiff === null || $absPast < $minPastDiff) {
                        $minPastDiff = $absPast;
                        $closestShift = $shift;
                    }
                    continue;
                }
            }

            if ($minutesToShift >= 0) {
                // future or now: prefer smallest non-negative
                if ($minFutureDiff === null || $minutesToShift < $minFutureDiff) {
                    $minFutureDiff = $minutesToShift;
                    $closestShift = $shift;
                }
            } else {
                // past: consider as fallback only if no future available
                $absPast = abs($minutesToShift);
                if ($minFutureDiff === null) {
                    if ($minPastDiff === null || $absPast < $minPastDiff) {
                        $minPastDiff = $absPast;
                        $closestShift = $shift;
                    }
                }
            }
        }

        return $closestShift;
    }

    /**
     * Korekcija vremena početka rada (zaokruživanje: 15-min blokovi).
     */
    private function adjustStartTime(Carbon $shiftStart, Carbon $entryTime): array
    {
        if ($entryTime->lessThanOrEqualTo($shiftStart))
            return [$shiftStart, 'none']; // nije zakasnio

        $minutesLate = $shiftStart->diffInMinutes($entryTime);

        if ($minutesLate <= 14) {
            // zaokruži na 15 min blok
            return [$shiftStart->copy()->addMinutes(15), 'minor15'];
        }
        elseif ($minutesLate <= 29) {
            // zaokruži na puni sat
            return [$shiftStart->copy()->addMinutes(30), 'minor30'];
        }
        else {
            // ako želiš: automatski označi "znatno kašnjenje"
            return [$shiftStart->copy()->addHour()->minute(0), 'major'];
        }
    }

    /**
     * Izdavanje izlaznice (private | business) samo dok je radnik prijavljen.
     */
    private function togglePass(Employee $employee, AttendanceRecord $openRecord, string $type): array
    {
        $now = Carbon::now();
        $openPass = \App\Models\Pass::where('employee_id', $employee->id)
            ->where('type', $type)
            ->where('status', 'open')
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();

        if ($openPass) {
            // Close existing pass
            $openPass->update([
                'end_time' => $now,
                'status' => 'closed',
            ]);
            return [
                'status' => 'pass-closed',
                'message' => $type === 'privatni' ? 'Privatna izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                'pass' => $openPass,
            ];
        }

        // Issue new pass
        $pass = \App\Models\Pass::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'reason' => '',
            'start_time' => $now,
            'end_time' => null,
            'approved_by' => null,
            'status' => 'open',
        ]);
        return [
            'status' => 'pass-open',
            'message' => $type === 'privatni' ? 'Privatna izlaznica izdana' : 'Službena izlaznica izdana',
            'pass' => $pass,
        ];
    }

    /**
     * Toggle pass at a given moment (offline scenario).
     */
    private function togglePassAtTime(Employee $employee, AttendanceRecord $openRecord, string $type, Carbon $moment): array
    {
        $openPass = \App\Models\Pass::where('employee_id', $employee->id)
            ->where('type', $type)
            ->where('status', 'open')
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();

        if ($openPass) {
            $openPass->update([
                'end_time' => $moment,
                'status' => 'closed',
            ]);
            return [
                'status' => 'pass-closed',
                'message' => $type === 'privatni' ? 'Privatna izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                'pass' => $openPass,
            ];
        }

        $pass = \App\Models\Pass::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'reason' => '',
            'start_time' => $moment,
            'end_time' => null,
            'approved_by' => null,
            'status' => 'open',
        ]);
        return [
            'status' => 'pass-open',
            'message' => $type === 'privatni' ? 'Privatna izlaznica izdana (offline)' : 'Službena izlaznica izdana (offline)',
            'pass' => $pass,
        ];
    }

    /**
     * Build a Carbon instance for the shift start on the same date as $moment.
     * Handles cases where shift->start_time is either 'H:i:s' or a full datetime string.
     */
    private function buildShiftStartForDate(string $shiftStartRaw, Carbon $moment): Carbon
    {
        try {
            // If raw contains a space, it's likely a full datetime; parse and override date
            if (strpos($shiftStartRaw, ' ') !== false) {
                $dt = Carbon::parse($shiftStartRaw);
                return $moment->copy()->setTime($dt->hour, $dt->minute, $dt->second);
            }
            // Time-only format
            [$h, $m, $s] = array_pad(explode(':', $shiftStartRaw), 3, 0);
            return $moment->copy()->setTime((int)$h, (int)$m, (int)$s);
        } catch (\Throwable $e) {
            // Fallback: use moment at top-of-hour to avoid crash
            return $moment->copy()->startOfHour();
        }
    }
}

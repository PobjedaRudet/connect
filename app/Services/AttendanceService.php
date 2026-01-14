<?php

namespace App\Services;

use App\Mail\PassCreatedMail;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Pass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
                $openPassAnyType = Pass::where('employee_id', $employee->id)
                    ->where('status', 'open')
                    ->whereNull('end_time')
                    ->latest('start_time')
                    ->first();

                if ($openPassAnyType) {
                    $closedPass = $this->closePassWithDuration($openPassAnyType, Carbon::now());
                    return [
                        'status' => 'pass-closed',
                        'message' => $openPassAnyType->type === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                        'pass' => $closedPass,
                    ];
                }

                return $this->togglePass($employee, $openRecord, $passType);
            }
            // No prefix: if there's an active (open) pass, close it automatically instead of checkout
            $activePass = Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
            if ($activePass) {
                $closedPass = $this->closePassWithDuration($activePass, Carbon::now());
                return [
                    'status' => 'pass-closed',
                    'message' => $activePass->type === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                    'pass' => $closedPass,
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
                $openPassAnyType = \App\Models\Pass::where('employee_id', $employee->id)
                    ->where('status', 'open')
                    ->whereNull('end_time')
                    ->latest('start_time')
                    ->first();

                if ($openPassAnyType) {
                    $closedPass = $this->closePassWithDuration($openPassAnyType, $moment);
                    return [
                        'status' => 'pass-closed',
                        'message' => $openPassAnyType->type === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                        'pass' => $closedPass,
                    ];
                }

                return $this->togglePassAtTime($employee, $openRecord, $passType, $moment);
            }
            // Auto-close pass if one is active
            $activePass = \App\Models\Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
            if ($activePass) {
                $closedPass = $this->closePassWithDuration($activePass, $moment);
                return [
                    'status' => 'pass-closed',
                    'message' => $activePass->type === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                    'pass' => $closedPass,
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
        $closest = $this->findClosestShift($employee, $moment);
        $shift = $closest['shift'] ?? null;
        $effectiveStart = null;
        $lateFlag = 'none';
        if ($shift && !empty($closest['start'])) {
            [$effectiveStart, $lateFlag] = $this->adjustStartTime($closest['start'], $moment);
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

        $closest = $this->findClosestShift($employee, $entryTime);
        $shift = $closest['shift'] ?? null;
        Log::debug('Closest shift for employee '.$employee->id.': '.($shift ? $shift->id : 'none'));

        $effectiveStart = null;
        $lateFlag = 'none';
        if ($shift && !empty($closest['start'])) {
            [$effectiveStart, $lateFlag] = $this->adjustStartTime($closest['start'], $entryTime);
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
    private function findClosestShift(Employee $employee, Carbon $entryTime): ?array
    {
        // Smjene su vezane za radnika preko pivot tabele employee_shift.
        if (!method_exists($employee, 'shifts')) {
            Log::warning("Employee model nema relaciju shifts(); findClosestShift ne može dohvatiti smjene za employee_id={$employee->id}");
            return null;
        }

        $shifts = $employee->shifts()->get();
        if ($shifts->isEmpty()) {
            return null;
        }

        $bestShift = null;
        $bestShiftStart = null;
        $bestAbsDiff = null;
        $bestSignedDiff = null;

        foreach ($shifts as $shift) {
            $baseStart = $this->buildShiftStartForDate($shift->start_time, $entryTime);

            // Consider same-day, previous-day and next-day starts (important around midnight).
            $candidates = [
                $baseStart->copy()->subDay(),
                $baseStart,
                $baseStart->copy()->addDay(),
            ];

            foreach ($candidates as $candidateStart) {
                $signedDiff = $entryTime->diffInMinutes($candidateStart, false); // negative => candidate in past
                $absDiff = abs($signedDiff);

                if ($bestAbsDiff === null || $absDiff < $bestAbsDiff) {
                    $bestAbsDiff = $absDiff;
                    $bestSignedDiff = $signedDiff;
                    $bestShift = $shift;
                    $bestShiftStart = $candidateStart;
                    continue;
                }

                // Tie-break: if equally close, prefer future (signedDiff >= 0)
                if ($absDiff === $bestAbsDiff) {
                    $bestIsFuture = $bestSignedDiff !== null && $bestSignedDiff >= 0;
                    $candidateIsFuture = $signedDiff >= 0;
                    if ($candidateIsFuture && !$bestIsFuture) {
                        $bestSignedDiff = $signedDiff;
                        $bestShift = $shift;
                        $bestShiftStart = $candidateStart;
                    }
                }
            }
        }

        if (!$bestShift || !$bestShiftStart) {
            return null;
        }

        return [
            'shift' => $bestShift,
            'start' => $bestShiftStart,
            'diff_minutes' => $bestSignedDiff,
        ];
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
            // znatno kašnjenje: effective_start = stvarno vrijeme ulaska
            return [$entryTime, 'major'];
        }
    }

    /**
     * ONLINE - Izdavanje izlaznice (private | business) samo dok je radnik prijavljen.
     */
    private function togglePass(Employee $employee, AttendanceRecord $openRecord, string $type): array
    {
        $now = Carbon::now();
        // Prefer matching type; if none, close any open pass for the employee before issuing new one
        $openPass = Pass::where('employee_id', $employee->id)
            ->where('type', $type)
            ->where('status', 'open')
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();

        if (!$openPass) {
            $openPass = Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
        }

        if ($openPass) {
            // Close existing pass (regardless of originally requested type) and compute duration
            $closedPass = $this->closePassWithDuration($openPass, $now);
            $closedType = $openPass->type === 'privatni' ? 'privatni' : 'službeni';
            return [
                'status' => 'pass-closed',
                'message' => $closedType === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                'pass' => $closedPass,
            ];
        }

        // Issue new pass
        $passStart = $now;
        if ($openRecord->effective_start && $passStart->lt($openRecord->effective_start)) {
            $passStart = $openRecord->effective_start;
        }
        $pass = Pass::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'reason' => '',
            'start_time' => $passStart,
            'end_time' => null,
            'approved_by' => null,
            'status' => 'open',
        ]);

        $this->sendPassCreatedEmailToSupervisors($employee, $pass);

        return [
            'status' => 'pass-open',
            'message' => $type === 'privatni' ? 'Izlaznica izdana' : 'Službena izlaznica izdana',
            'pass' => $pass,
        ];
    }

    /**
     * OFFLINE - Toggle pass at a given moment (OFFLINE scenario).
     */
    private function togglePassAtTime(Employee $employee, AttendanceRecord $openRecord, string $type, Carbon $moment): array
    {
        // Prefer matching type; if none, close any open pass for the employee before issuing new one
        $openPass = Pass::where('employee_id', $employee->id)
            ->where('type', $type)
            ->where('status', 'open')
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();

        if (!$openPass) {
            $openPass = Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
        }

        if ($openPass) {
            $closedPass = $this->closePassWithDuration($openPass, $moment);
            $msgType = $openPass->type === 'privatni' ? 'privatni' : 'službeni';
            return [
                'status' => 'pass-closed',
                'message' => $msgType === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                'pass' => $closedPass,
            ];
        }

        $passStart = $moment;
        if ($openRecord->effective_start && $passStart->lt($openRecord->effective_start)) {
            $passStart = $openRecord->effective_start;
        }
        $pass = Pass::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'reason' => '',
            'start_time' => $passStart,
            'end_time' => null,
            'approved_by' => null,
            'status' => 'open',
        ]);

        $this->sendPassCreatedEmailToSupervisors($employee, $pass);

        return [
            'status' => 'pass-open',
            'message' => $type === 'privatni' ? 'Izlaznica izdana (offline)' : 'Službena izlaznica izdana (offline)',
            'pass' => $pass,
        ];
    }

    private function sendPassCreatedEmailToSupervisors(Employee $employee, Pass $pass): void
    {
        try {
            Log::info('Pass created: preparing notification email', [
                'employee_id' => $employee->id ?? null,
                'pass_id' => $pass->id ?? null,
                'pass_type' => $pass->type ?? null,
                'mail_default' => config('mail.default'),
                'from' => config('mail.from.address'),
                'nadlezne_osobe' => $employee->nadlezne_osobe ?? null,
            ]);

            $emails = $this->resolveSupervisorEmails($employee);
            if (empty($emails)) {
                Log::warning('Pass created email skipped: no supervisor emails resolved', [
                    'employee_id' => $employee->id ?? null,
                    'pass_id' => $pass->id ?? null,
                    'nadlezne_osobe' => $employee->nadlezne_osobe ?? null,
                ]);
                return;
            }

            Log::info('Sending pass created email', [
                'employee_id' => $employee->id ?? null,
                'pass_id' => $pass->id ?? null,
                'recipients' => $emails,
            ]);

            Mail::to($emails)->send(new PassCreatedMail($pass, $employee));
        } catch (\Throwable $e) {
            Log::warning('Failed to send pass created email', [
                'employee_id' => $employee->id ?? null,
                'pass_id' => $pass->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reads employees.nadlezne_osobe (JSON/scalar) as list of USER ids (users.id) and resolves their emails.
     * Accepts either array, JSON string, numeric string, or integer.
     *
     * @return string[]
     */
    private function resolveSupervisorEmails(Employee $employee): array
    {
        $raw = $employee->nadlezne_osobe ?? null;

        // Some records may store a single supervisor id as a scalar (e.g. 260) instead of JSON array.
        if (is_int($raw)) {
            $raw = [$raw];
        }
        if (is_string($raw)) {
            // numeric string => treat as a single id
            if (ctype_digit($raw)) {
                $raw = [(int) $raw];
            }
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_int($value) || is_string($value)) {
                $value = (int) $value;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return [];
        }

        $supervisors = User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'email']);

        $foundIds = $supervisors->pluck('id')->map(fn ($v) => (int) $v)->all();
        $missingIds = array_values(array_diff($ids, $foundIds));
        if (!empty($missingIds)) {
            Log::warning('Pass email: supervisor ids not found in users table', [
                'employee_id' => $employee->id ?? null,
                'missing_supervisor_ids' => $missingIds,
            ]);
        }

        $missingEmailIds = $supervisors
            ->filter(fn ($s) => empty($s->email) || trim((string) $s->email) === '')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if (!empty($missingEmailIds)) {
            Log::warning('Pass email: supervisor users have no email set', [
                'employee_id' => $employee->id ?? null,
                'supervisor_ids_missing_email' => $missingEmailIds,
            ]);
        }

        return $supervisors
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn ($email) => trim((string) $email))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Close a pass and persist its duration in minutes.
     */
    private function closePassWithDuration(Pass $pass, ?Carbon $endTime): Pass
    {
        if (!$endTime) {
            $pass->update([
                'end_time' => null,
                'status' => 'closed',
                'duration_minutes' => null,
            ]);

            return $pass;
        }

        $start = $pass->start_time ?: $endTime;

        // If the employee returns before the pass actually starts (e.g. pass start_time was
        // shifted to effective_start), duration must be 0.
        if ($start && $endTime->lessThanOrEqualTo($start)) {
            $pass->update([
                'end_time' => $endTime,
                'status' => 'closed',
                'duration_minutes' => 0,
            ]);

            return $pass;
        }

        // If privatni, count until end of workday; if službeni, count until actual return time.
        $endReference = $pass->type === 'privatni'
            ? $this->resolveWorkdayEnd($start)
            : $endTime;

        $durationMinutes = $start ? max($start->diffInMinutes($endReference, false), 0) : null;

        $pass->update([
            'end_time' => $endTime,
            'status' => 'closed',
            'duration_minutes' => $durationMinutes,
        ]);

        return $pass;
    }

    private function resolveWorkdayEnd(Carbon $start): Carbon
    {
        $endTimeString = config('app.workday_end_time', '15:00');

        try {
            return Carbon::parse($start->toDateString() . ' ' . $endTimeString);
        } catch (\Throwable $e) {
            return $start->copy()->setTime(15, 0);
        }
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

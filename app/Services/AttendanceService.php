<?php

namespace App\Services;

use App\Jobs\SendEarlyDepartureApprovalEmailJob;
use App\Jobs\SendLateArrivalApprovalEmailJob;
use App\Jobs\SendPassCreatedEmailJob;
use App\Mail\EarlyDepartureApprovalMail;
use App\Mail\LateArrivalApprovalMail;
use App\Mail\PassCreatedMail;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Pass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class AttendanceService
{
    private const PASS_TOGGLE_DEBOUNCE_SECONDS = 5;

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

        $employee = Employee::where('rfid_code', $rfidTrimmed)
            ->select(['id', 'dept', 'pass_approvers', 'firstName', 'lastName'])
            ->first();

        if (!$employee) {
            return ['status' => 'error', 'message' => 'Nepoznata kartica!'];
        }

        $openRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNull('exit_time')
            ->first();

        if ($openRecord) {
            // If a pass prefix while working: toggle pass (issue or close)
            if ($passType) {
                $now = Carbon::now();
                $openPassAnyType = Pass::where('employee_id', $employee->id)
                    ->where('status', 'open')
                    ->whereNull('end_time')
                    ->latest('start_time')
                    ->first();

                if ($openPassAnyType) {
                    $maybeClosed = $this->closePassUnlessDuplicateScan($openPassAnyType, $now);
                    if (!$maybeClosed) {
                        return $this->scanResult([
                            'status' => 'pass-open',
                            'message' => $openPassAnyType->type === 'privatni' ? 'Izlaznica već otvorena' : 'Službena izlaznica već otvorena',
                            'pass' => $openPassAnyType,
                        ], $employee);
                    }
                    return $this->scanResult([
                        'status' => 'pass-closed',
                        'message' => $openPassAnyType->type === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                        'pass' => $maybeClosed,
                    ], $employee);
                }

                $recentlyClosed = $this->findRecentlyClosedPass($employee->id, $now);
                if ($recentlyClosed) {
                    return $this->scanResult([
                        'status' => 'pass-closed',
                        'message' => $passType === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                        'pass' => $recentlyClosed,
                    ], $employee);
                }

                return $this->scanResult($this->togglePass($employee, $openRecord, $passType, true), $employee);
            }
            // No prefix: if there's an active (open) pass, close it automatically instead of checkout
            $activePass = Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
            if ($activePass) {
                $now = Carbon::now();
                $maybeClosed = $this->closePassUnlessDuplicateScan($activePass, $now);
                if (!$maybeClosed) {
                    return $this->scanResult([
                        'status' => 'pass-open',
                        'message' => $activePass->type === 'privatni' ? 'Izlaznica već otvorena' : 'Službena izlaznica već otvorena',
                        'pass' => $activePass,
                    ], $employee);
                }
                return $this->scanResult([
                    'status' => 'pass-closed',
                    'message' => $activePass->type === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                    'pass' => $maybeClosed,
                ], $employee);
            }
            // Guard: if a pass was just closed moments ago, do not interpret a duplicate scan as checkout
            $now = Carbon::now();
            $recentlyClosed = $this->findRecentlyClosedPass($employee->id, $now);
            if ($recentlyClosed) {
                return $this->scanResult([
                    'status' => 'pass-closed',
                    'message' => $recentlyClosed->type === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                    'pass' => $recentlyClosed,
                ], $employee);
            }
            // No active pass: proceed with checkout
            return $this->scanResult($this->checkout($employee, $openRecord, $terminalId), $employee);
        }

        // If no open record, pass cannot be issued; treat as check-in
        if ($passType) {
            return ['status' => 'error', 'message' => 'Radnik nije prijavljen; izlaznica se može izdati samo tokom rada.'];
        }
        return $this->scanResult($this->checkin($employee, $terminalId), $employee);
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

        $employee = Employee::where('rfid_code', $rfidTrimmed)
            ->select(['id', 'dept', 'pass_approvers', 'firstName', 'lastName'])
            ->first();
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
                    $maybeClosed = $this->closePassUnlessDuplicateScan($openPassAnyType, $moment);
                    if (!$maybeClosed) {
                        return $this->scanResult([
                            'status' => 'pass-open',
                            'message' => $openPassAnyType->type === 'privatni' ? 'Izlaznica već otvorena (offline)' : 'Službena izlaznica već otvorena (offline)',
                            'pass' => $openPassAnyType,
                        ], $employee);
                    }
                    return $this->scanResult([
                        'status' => 'pass-closed',
                        'message' => $openPassAnyType->type === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                        'pass' => $maybeClosed,
                    ], $employee);
                }

                $recentlyClosed = $this->findRecentlyClosedPass($employee->id, $moment);
                if ($recentlyClosed) {
                    return $this->scanResult([
                        'status' => 'pass-closed',
                        'message' => $passType === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                        'pass' => $recentlyClosed,
                    ], $employee);
                }

                return $this->scanResult($this->togglePassAtTime($employee, $openRecord, $passType, $moment, true), $employee);
            }
            // Auto-close pass if one is active
            $activePass = \App\Models\Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();
            if ($activePass) {
                $maybeClosed = $this->closePassUnlessDuplicateScan($activePass, $moment);
                if (!$maybeClosed) {
                    return [
                        'status' => 'pass-open',
                        'message' => $activePass->type === 'privatni' ? 'Izlaznica već otvorena (offline)' : 'Službena izlaznica već otvorena (offline)',
                        'pass' => $activePass,
                    ];
                }
                return [
                    'status' => 'pass-closed',
                    'message' => $activePass->type === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                    'pass' => $maybeClosed,
                ];
            }
            // Guard: if a pass was just closed moments ago, do not interpret a duplicate scan as checkout
            $recentlyClosed = $this->findRecentlyClosedPass($employee->id, $moment);
            if ($recentlyClosed) {
                return [
                    'status' => 'pass-closed',
                    'message' => $recentlyClosed->type === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                    'pass' => $recentlyClosed,
                ];
            }
            // Checkout with provided timestamp
            $openRecord->update([
                'exit_time' => $moment,
                'status' => 'left',
                'terminal_out' => $terminalId ?? $openRecord->terminal_out,
            ]);
            return [
                'status' => 'checkout',
                'message' => 'Odjava (offline)',
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
            'terminal_in' => $terminalId,
        ]);

        if ($lateFlag !== 'none' && $shift && !empty($closest['start'])) {
            $this->createLatePass($employee, $closest['start'], $moment);
        }

        return [
            'status' => 'checkin',
            'message' => 'Prijava (offline)',
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
            'terminal_in' => $terminalId,
        ]);

        if ($lateFlag !== 'none' && $shift && !empty($closest['start'])) {
            $this->createLatePass($employee, $closest['start'], $entryTime);
        }

        return [
            'status' => 'checkin',
            'message' => 'Prijava',
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
            'terminal_out' => $terminalId ?? $record->terminal_out,
        ]);

        $shiftEnd = $this->resolveShiftEndForRecord($record, $exitTime);
        if ($shiftEnd && $exitTime->lessThan($shiftEnd)) {
            $this->createEarlyDeparturePass($employee, $exitTime, $shiftEnd);
        }

        return [
            'status' => 'checkout',
            'message' => 'Odjava',
            'record' => $record,
        ];
    }

    /**
     * Pronalazi najbližu smjenu radniku.
     */
    private function findClosestShift(Employee $employee, Carbon $entryTime): ?array
    {
        $shifts = $this->getDepartmentShiftsForEmployee($employee);

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

    private function getDepartmentShiftsForEmployee(Employee $employee): Collection
    {
        $deptRaw = $employee->dept ?? null;
        $deptId = is_numeric($deptRaw) ? (int) $deptRaw : null;
        if (!$deptId) {
            return collect();
        }

        $shifts = Cache::remember("shifts:dept:{$deptId}", 3600, function () use ($deptId) {
            return \App\Models\Shift::query()
                ->where('department_id', $deptId)
                ->get();
        });

        if ($shifts->isNotEmpty()) {
            Log::debug("Shift by department: employee_id={$employee->id} department_id={$deptId}");
        }

        return $shifts;
    }

    /**
     * Korekcija vremena početka rada (zaokruživanje: 15-min blokovi).
     */
    private function adjustStartTime(Carbon $shiftStart, Carbon $entryTime): array
    {
        if ($entryTime->lessThanOrEqualTo($shiftStart))
            return [$shiftStart, 'none']; // nije zakasnio

        $minutesLate = $shiftStart->diffInMinutes($entryTime);

        // Stvarno kašnjenje — effective_start = stvarno ulazno vrijeme (bez zaokruživanja)
        if ($minutesLate <= 29) {
            return [$entryTime, 'minor'];
        }

        return [$entryTime, 'major'];
    }

    /**
     * ONLINE - Izdavanje izlaznice (private | business) samo dok je radnik prijavljen.
     *
     * @param  bool  $preflightDone  true when processScan already verified no open/recently-closed pass
     */
    private function togglePass(Employee $employee, AttendanceRecord $openRecord, string $type, bool $preflightDone = false): array
    {
        $now = Carbon::now();

        if ($preflightDone) {
            return $this->issueNewPass($employee, $openRecord, $type, $now);
        }

        return DB::transaction(function () use ($employee, $openRecord, $type, $now) {
            $openPass = Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->lockForUpdate()
                ->first();

            if ($openPass) {
                $closedPass = $this->closePassUnlessDuplicateScan($openPass, $now);
                if (!$closedPass) {
                    return [
                        'status' => 'pass-open',
                        'message' => $openPass->type === 'privatni' ? 'Izlaznica već otvorena' : 'Službena izlaznica već otvorena',
                        'pass' => $openPass,
                    ];
                }
                $closedType = $openPass->type === 'privatni' ? 'privatni' : 'službeni';
                return [
                    'status' => 'pass-closed',
                    'message' => $closedType === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                    'pass' => $closedPass,
                ];
            }

            $recentlyClosed = $this->findRecentlyClosedPass($employee->id, $now);
            if ($recentlyClosed) {
                return [
                    'status' => 'pass-closed',
                    'message' => $type === 'privatni' ? 'Izlaznica zatvorena' : 'Službena izlaznica zatvorena',
                    'pass' => $recentlyClosed,
                ];
            }

            return $this->issueNewPass($employee, $openRecord, $type, $now);
        });
    }

    /**
     * OFFLINE - Toggle pass at a given moment (OFFLINE scenario).
     *
     * @param  bool  $preflightDone  true when processOfflineScan already verified no open/recently-closed pass
     */
    private function togglePassAtTime(Employee $employee, AttendanceRecord $openRecord, string $type, Carbon $moment, bool $preflightDone = false): array
    {
        if ($preflightDone) {
            return $this->issueNewPass($employee, $openRecord, $type, $moment, true);
        }

        return DB::transaction(function () use ($employee, $openRecord, $type, $moment) {
            $openPass = Pass::where('employee_id', $employee->id)
                ->where('status', 'open')
                ->whereNull('end_time')
                ->latest('start_time')
                ->lockForUpdate()
                ->first();

            if ($openPass) {
                $closedPass = $this->closePassUnlessDuplicateScan($openPass, $moment);
                if (!$closedPass) {
                    return [
                        'status' => 'pass-open',
                        'message' => $openPass->type === 'privatni' ? 'Izlaznica već otvorena (offline)' : 'Službena izlaznica već otvorena (offline)',
                        'pass' => $openPass,
                    ];
                }
                $msgType = $openPass->type === 'privatni' ? 'privatni' : 'službeni';
                return [
                    'status' => 'pass-closed',
                    'message' => $msgType === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                    'pass' => $closedPass,
                ];
            }

            $recentlyClosed = $this->findRecentlyClosedPass($employee->id, $moment);
            if ($recentlyClosed) {
                return [
                    'status' => 'pass-closed',
                    'message' => $type === 'privatni' ? 'Izlaznica zatvorena (offline)' : 'Službena izlaznica zatvorena (offline)',
                    'pass' => $recentlyClosed,
                ];
            }

            return $this->issueNewPass($employee, $openRecord, $type, $moment, true);
        });
    }

    private function issueNewPass(
        Employee $employee,
        AttendanceRecord $openRecord,
        string $type,
        Carbon $moment,
        bool $offline = false,
    ): array {
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

        $this->deferPassCreatedEmail($employee->id, $pass->id);

        if ($offline) {
            $message = $type === 'privatni' ? 'Izlaznica izdana (offline)' : 'Službena izlaznica izdana (offline)';
        } else {
            $message = $type === 'privatni' ? 'Izlaznica izdana' : 'Službena izlaznica izdana';
        }

        return [
            'status' => 'pass-open',
            'message' => $message,
            'pass' => $pass,
        ];
    }

    /**
     * Creates a retroactive izlaznica (pass) for the late period and defers the approval email.
     * start_time = scheduled shift start, end_time = actual entry, type defaults to 'privatni'.
     * The supervisor receives an email with two signed links to reclassify as privatna/službena.
     */
    private function createLatePass(Employee $employee, Carbon $shiftStart, Carbon $entryTime): void
    {
        $minutesLate = (int) $shiftStart->diffInMinutes($entryTime, false);
        if ($minutesLate <= 0) {
            return;
        }

        try {
            $pass = Pass::create([
                'employee_id'    => $employee->id,
                'type'           => 'privatni', // default; supervisor may change via email link
                'reason'         => "Automatski kreirana izlaznica za kašnjenje od {$minutesLate} min",
                'start_time'     => $shiftStart,
                'end_time'       => $entryTime,
                'status'         => 'closed', // the late period has already passed
                'approved'       => false,
                'late_pass'      => true,
                'late_minutes'   => $minutesLate,
                'duration_minutes' => $minutesLate,
            ]);

            SendLateArrivalApprovalEmailJob::dispatch($employee->id, $pass->id)->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('Failed to create late pass or dispatch approval email', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sends the late-arrival approval email to the employee's pass approvers.
     * Called by SendLateArrivalApprovalEmailJob.
     */
    public function deliverLateArrivalApprovalEmail(int $employeeId, int $passId): void
    {
        try {
            $employee = Employee::query()
                ->select(['id', 'firstName', 'lastName', 'pass_approvers'])
                ->find($employeeId);
            $pass = Pass::query()->find($passId);

            if (!$employee || !$pass) {
                return;
            }

            $emails = $this->resolvePassApproverEmails($employee);
            if (empty($emails)) {
                Log::info('Late arrival approval: no approver emails found for employee', [
                    'employee_id' => $employeeId,
                    'pass_id'     => $passId,
                ]);
                return;
            }

            Mail::to($emails)->queue(new LateArrivalApprovalMail($pass, $employee));
        } catch (\Throwable $e) {
            Log::warning('Failed to queue late arrival approval email', [
                'employee_id' => $employeeId,
                'pass_id'     => $passId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolves the scheduled shift-end Carbon moment for a record.
     * Mirrors the logic in AttendanceRecord::resolveShiftEndMoment().
     */
    private function resolveShiftEndForRecord(AttendanceRecord $record, Carbon $workStart): ?Carbon
    {
        if (!$record->shift_id) {
            return null;
        }

        $shift = $record->shift()->first();
        if (!$shift || !$shift->start_time || !$shift->end_time) {
            return null;
        }

        $tz          = config('app.timezone');
        $workDate    = $workStart->copy()->timezone($tz)->toDateString();
        $startTimeStr = $shift->start_time instanceof Carbon
            ? $shift->start_time->format('H:i:s')
            : (string) $shift->start_time;
        $endTimeStr = $shift->end_time instanceof Carbon
            ? $shift->end_time->format('H:i:s')
            : (string) $shift->end_time;

        try {
            $shiftStart = Carbon::parse($workDate . ' ' . $startTimeStr, $tz);
            $shiftEnd   = Carbon::parse($workDate . ' ' . $endTimeStr, $tz);
        } catch (\Throwable) {
            return null;
        }

        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        return $shiftEnd;
    }

    /**
     * Creates a retroactive izlaznica for the early-departure period and defers approval email.
     * start_time = actual exit, end_time = scheduled shift end, type defaults to 'privatni'.
     */
    private function createEarlyDeparturePass(Employee $employee, Carbon $exitTime, Carbon $shiftEnd): void
    {
        $minutesEarly = (int) $exitTime->diffInMinutes($shiftEnd, false);
        if ($minutesEarly <= 0) {
            return;
        }

        try {
            $pass = Pass::create([
                'employee_id'     => $employee->id,
                'type'            => 'privatni',
                'reason'          => "Automatski kreirana izlaznica za prijevremeni odlazak od {$minutesEarly} min",
                'start_time'      => $exitTime,
                'end_time'        => $shiftEnd,
                'status'          => 'closed',
                'approved'        => false,
                'early_departure' => true,
                'early_minutes'   => $minutesEarly,
                'duration_minutes' => $minutesEarly,
            ]);

            SendEarlyDepartureApprovalEmailJob::dispatch($employee->id, $pass->id)->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('Failed to create early departure pass or dispatch approval email', [
                'employee_id' => $employee->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sends the early-departure approval email to the employee's pass approvers.
     * Called by SendEarlyDepartureApprovalEmailJob.
     */
    public function deliverEarlyDepartureApprovalEmail(int $employeeId, int $passId): void
    {
        try {
            $employee = Employee::query()
                ->select(['id', 'firstName', 'lastName', 'pass_approvers'])
                ->find($employeeId);
            $pass = Pass::query()->find($passId);

            if (!$employee || !$pass) {
                return;
            }

            $emails = $this->resolvePassApproverEmails($employee);
            if (empty($emails)) {
                Log::info('Early departure approval: no approver emails found for employee', [
                    'employee_id' => $employeeId,
                    'pass_id'     => $passId,
                ]);
                return;
            }

            Mail::to($emails)->queue(new EarlyDepartureApprovalMail($pass, $employee));
        } catch (\Throwable $e) {
            Log::warning('Failed to queue early departure approval email', [
                'employee_id' => $employeeId,
                'pass_id'     => $passId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function deferPassCreatedEmail(int $employeeId, int $passId): void
    {
        SendPassCreatedEmailJob::dispatch($employeeId, $passId)->afterResponse();
    }

    public function deliverPassCreatedEmail(int $employeeId, int $passId): void
    {
        try {
            $employee = Employee::query()
                ->select(['id', 'firstName', 'lastName', 'pass_approvers'])
                ->find($employeeId);
            $pass = Pass::query()->find($passId);

            if (!$employee || !$pass) {
                return;
            }

            $emails = $this->resolvePassApproverEmails($employee);
            if (empty($emails)) {
                return;
            }

            Mail::to($emails)->queue(new PassCreatedMail($pass, $employee));
        } catch (\Throwable $e) {
            Log::warning('Failed to queue pass created email', [
                'employee_id' => $employeeId,
                'pass_id' => $passId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reads employees.pass_approvers (JSON/scalar) as list of USER ids (users.id) and resolves their emails.
     * Accepts either array, JSON string, numeric string, or integer.
     *
     * @return string[]
     */
    private function resolvePassApproverEmails(Employee $employee): array
    {
        $raw = $employee->pass_approvers ?? null;

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
            Log::warning('Pass email: pass approver ids not found in users table', [
                'employee_id' => $employee->id ?? null,
                'missing_pass_approver_ids' => $missingIds,
            ]);
        }

        $missingEmailIds = $supervisors
            ->filter(fn ($s) => empty($s->email) || trim((string) $s->email) === '')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if (!empty($missingEmailIds)) {
            Log::warning('Pass email: pass approver users have no email set', [
                'employee_id' => $employee->id ?? null,
                'pass_approver_ids_missing_email' => $missingEmailIds,
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

        // If privatni, count until actual return time (but cap to end of workday if the
        // return is after workday end). If službeni, always count until actual return time.
        $endReference = $endTime;
        if ($pass->type === 'privatni') {
            $workdayEnd = $this->resolveWorkdayEnd($start);
            if ($workdayEnd->greaterThan($start) && $endTime->greaterThan($workdayEnd)) {
                $endReference = $workdayEnd;
            }
        }

        $durationMinutes = $start ? max($start->diffInMinutes($endReference, false), 0) : null;

        $pass->update([
            'end_time' => $endTime,
            'status' => 'closed',
            'duration_minutes' => $durationMinutes,
        ]);

        return $pass;
    }

    /**
     * Returns closed Pass if it should be closed, or null if this looks like a duplicate scan
     * occurring immediately after opening the pass.
     */
    private function closePassUnlessDuplicateScan(Pass $pass, Carbon $moment): ?Pass
    {
        $start = $pass->start_time;
        if ($start instanceof Carbon) {
            $secondsSinceStart = $start->diffInSeconds($moment, false);
            if ($secondsSinceStart >= 0 && $secondsSinceStart <= self::PASS_TOGGLE_DEBOUNCE_SECONDS) {
                return null;
            }
        }

        return $this->closePassWithDuration($pass, $moment);
    }

    private function findRecentlyClosedPass(int $employeeId, Carbon $moment): ?Pass
    {
        $threshold = $moment->copy()->subSeconds(self::PASS_TOGGLE_DEBOUNCE_SECONDS);

        return Pass::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'closed')
            ->whereNotNull('end_time')
            ->where('end_time', '>=', $threshold)
            ->latest('end_time')
            ->first();
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

    private function scanResult(array $result, ?Employee $employee = null): array
    {
        if (!$employee) {
            return $result;
        }

        $fullName = trim((string) ($employee->firstName ?? '') . ' ' . (string) ($employee->lastName ?? ''));
        $result['employee_id'] = $employee->id;

        if ($fullName !== '') {
            $result['employee_full_name'] = $fullName;

            if (isset($result['pass']) && is_object($result['pass'])) {
                $result['pass']->full_name = $fullName;
            }

            if (isset($result['record']) && is_object($result['record'])) {
                $result['record']->full_name = $fullName;
            }
        }

        return $result;
    }
}

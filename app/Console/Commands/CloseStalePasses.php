<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Pass;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseStalePasses extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:close-stale-passes';

    /**
     * The console command description.
     */
    protected $description = 'Zatvara otvorene izlaznice na kraju dana, dodjeljujući end_time do kraja smjene.';

    public function handle(): int
    {
        $now = Carbon::now();

        $passes = Pass::with('employee')
            ->whereNull('end_time')
            ->where('status', 'open')
            ->get();

            Log::info('app:close-stale-passes found passes to close', ['count' => $passes->count()]);

        $closedCount = 0;
        $closedAttendanceCount = 0;

        foreach ($passes as $pass) {
            if (!$pass->start_time) {
                continue;
            }

            $endTime = $this->resolveShiftEnd($pass->employee_id, $pass->start_time);
            Log::info('Resolving end time for pass', ['pass_id' => $pass->id, 'resolved_end_time' => $endTime]);
            if (!$endTime) {
                continue;
            }

            if ($endTime->greaterThan($now)) {
                continue;
            }

            $durationMinutes = max($pass->start_time->diffInMinutes($endTime, false), 0);

            $pass->update([
                'end_time' => $endTime,
                'status' => 'closed',
                'duration_minutes' => $durationMinutes,
            ]);

            $closedCount++;

            if ($this->closeOpenAttendanceRecord($pass->employee_id, $endTime)) {
                $closedAttendanceCount++;
            }
        }

        $this->info("Zatvoreno izlaznica: {$closedCount}");
        $this->info("Zatvoreno evidencija prisustva: {$closedAttendanceCount}");
        if ($closedCount > 0) {
            Log::info('app:close-stale-passes closed passes', ['count' => $closedCount]);
        }
        if ($closedAttendanceCount > 0) {
            Log::info('app:close-stale-passes closed attendance records', ['count' => $closedAttendanceCount]);
        }

        return Command::SUCCESS;
    }

    private function closeOpenAttendanceRecord(int $employeeId, Carbon $endTime): bool
    {
        $record = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereNull('exit_time')
            ->latest('entry_time')
            ->first();

        if (!$record) {
            return false;
        }

        $record->update([
            'exit_time' => $endTime,
            'status' => 'left',
        ]);

        Log::info('Closed attendance record with stale pass', [
            'employee_id' => $employeeId,
            'attendance_record_id' => $record->id,
            'exit_time' => $endTime,
        ]);

        return true;
    }

    private function resolveShiftEnd(int $employeeId, Carbon $start): ?Carbon
    {
        $shift = $this->findShiftForMoment($employeeId, $start);
        Log::info('Resolving shift end', ['employee_id' => $employeeId, 'shift_id' => $shift?->id]);

        $fallbackEnd = config('app.workday_end_time', '15:30');
        if (!$shift) {
            try {
                $end = Carbon::parse($start->toDateString() . ' ' . $fallbackEnd);
                if ($end->lessThan($start)) {
                    $end->addDay();
                }
                return $end;
            } catch (\Throwable $e) {
                return null;
            }
        }

        $shiftStart = $this->buildShiftTimeForDate($shift->start_time, $start);
        $shiftEnd = $this->buildShiftTimeForDate($shift->end_time, $start);
        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        return $shiftEnd;
    }

    private function findShiftForMoment(int $employeeId, Carbon $moment): ?Shift
    {
        $employee = Employee::query()->find($employeeId);
        $deptRaw = $employee?->dept;
        $deptId = is_numeric($deptRaw) ? (int) $deptRaw : null;

        $shifts = $deptId
            ? Shift::query()->where('department_id', $deptId)->get()
            : collect();

        if ($shifts->isEmpty()) {
            return null;
        }

        // Prefer the shift that actually contains the moment.
        $containing = [];
        foreach ($shifts as $shift) {
            $baseStart = $this->buildShiftTimeForDate($shift->start_time, $moment);
            $candidates = [
                $baseStart->copy()->subDay(),
                $baseStart,
                $baseStart->copy()->addDay(),
            ];

            foreach ($candidates as $candidateStart) {
                $candidateEnd = $this->buildShiftTimeForDate($shift->end_time, $candidateStart);
                if ($candidateEnd->lessThanOrEqualTo($candidateStart)) {
                    $candidateEnd->addDay();
                }

                if ($moment->betweenIncluded($candidateStart, $candidateEnd)) {
                    $containing[] = ['shift' => $shift, 'end' => $candidateEnd];
                    break;
                }
            }
        }

        if (!empty($containing)) {
            usort($containing, fn ($a, $b) => $a['end']->timestamp <=> $b['end']->timestamp);
            return $containing[0]['shift'];
        }

        // Fallback: closest shift start by absolute diff, prefer future on ties.
        $best = null;
        $bestAbsDiff = null;
        $bestSignedDiff = null;

        foreach ($shifts as $shift) {
            $baseStart = $this->buildShiftTimeForDate($shift->start_time, $moment);
            $candidates = [
                $baseStart->copy()->subDay(),
                $baseStart,
                $baseStart->copy()->addDay(),
            ];

            foreach ($candidates as $candidateStart) {
                $signedDiff = $moment->diffInMinutes($candidateStart, false);
                $absDiff = abs($signedDiff);

                if ($bestAbsDiff === null || $absDiff < $bestAbsDiff) {
                    $bestAbsDiff = $absDiff;
                    $bestSignedDiff = $signedDiff;
                    $best = $shift;
                    continue;
                }

                if ($absDiff === $bestAbsDiff) {
                    $bestIsFuture = $bestSignedDiff !== null && $bestSignedDiff >= 0;
                    $candidateIsFuture = $signedDiff >= 0;
                    if ($candidateIsFuture && !$bestIsFuture) {
                        $bestSignedDiff = $signedDiff;
                        $best = $shift;
                    }
                }
            }
        }

        return $best;
    }

    private function buildShiftTimeForDate($shiftTimeRaw, Carbon $moment): Carbon
    {
        try {
            $raw = $shiftTimeRaw instanceof Carbon
                ? $shiftTimeRaw->format('H:i:s')
                : (string) $shiftTimeRaw;

            if (strpos($raw, ' ') !== false) {
                $dt = Carbon::parse($raw);
                return $moment->copy()->setTime($dt->hour, $dt->minute, $dt->second);
            }

            [$h, $m, $s] = array_pad(explode(':', $raw), 3, 0);
            return $moment->copy()->setTime((int) $h, (int) $m, (int) $s);
        } catch (\Throwable $e) {
            return $moment->copy()->startOfHour();
        }
    }
}

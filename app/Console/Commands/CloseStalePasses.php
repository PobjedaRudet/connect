<?php

namespace App\Console\Commands;

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
        $passes = Pass::with('employee')
            ->whereNull('end_time')
            ->where('status', 'open')
            ->get();

            Log::info('app:close-stale-passes found passes to close', ['count' => $passes->count()]);

        $closedCount = 0;

        foreach ($passes as $pass) {
            if (!$pass->start_time) {
                continue;
            }

            $endTime = $this->resolveShiftEnd($pass->employee_id, $pass->start_time);
            Log::info('Resolving end time for pass', ['pass_id' => $pass->id, 'resolved_end_time' => $endTime]);
            if (!$endTime) {
                continue;
            }

            $durationMinutes = max($pass->start_time->diffInMinutes($endTime, false), 0);

            $pass->update([
                'end_time' => $endTime,
                'status' => 'closed',
                'duration_minutes' => $durationMinutes,
            ]);

            $closedCount++;
        }

        $this->info("Zatvoreno izlaznica: {$closedCount}");
        if ($closedCount > 0) {
            Log::info('app:close-stale-passes closed passes', ['count' => $closedCount]);
        }

        return Command::SUCCESS;
    }

    private function resolveShiftEnd(int $employeeId, Carbon $start): ?Carbon
    {
        $shift = Shift::query()
            ->join('employee_shift', 'shifts.id', '=', 'employee_shift.shift_id')
            ->where('employee_shift.employee_id', $employeeId)
            ->orderByDesc('shifts.end_time')
            ->select('shifts.end_time')
            ->first();
            Log::info('Resolving shift end', ['employee_id' => $employeeId, 'shift' => $shift]);

            $fallbackEnd = config('app.workday_end_time', '15:30');
            $rawEnd = $shift?->end_time;
            $endTimeStr = $rawEnd instanceof Carbon
                ? $rawEnd->format('H:i:s')
                : ($rawEnd ?: $fallbackEnd);

        try {
            $end = Carbon::parse($start->toDateString() . ' ' . $endTimeStr);
            // If shift end is before start (overnight), move to next day
            if ($end->lessThan($start)) {
                $end->addDay();
            }
            return $end;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

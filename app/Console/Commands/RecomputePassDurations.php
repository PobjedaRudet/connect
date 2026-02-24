<?php

namespace App\Console\Commands;

use App\Models\Pass;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecomputePassDurations extends Command
{
    protected $signature = 'app:recompute-pass-durations {--from=} {--to=} {--dry-run}';

    protected $description = 'Recalculates duration_minutes for closed passes using start_time/end_time (privatni capped to workday end).';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $dryRun = (bool) $this->option('dry-run');

        if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from .= ' 00:00:00';
        }
        if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to .= ' 23:59:59';
        }

        $query = Pass::query()
            ->whereNotNull('start_time')
            ->whereNotNull('end_time');

        if ($from) {
            $query->where('start_time', '>=', $from);
        }
        if ($to) {
            $query->where('start_time', '<=', $to);
        }

        $total = 0;
        $changed = 0;

        $workdayEndString = config('app.workday_end_time', '15:00');

        $query->orderBy('id')->chunk(500, function ($passes) use (&$total, &$changed, $dryRun, $workdayEndString) {
            foreach ($passes as $pass) {
                $total++;

                $start = $pass->start_time ? Carbon::parse($pass->start_time) : null;
                $endTime = $pass->end_time ? Carbon::parse($pass->end_time) : null;

                if (!$start || !$endTime) {
                    continue;
                }

                if ($endTime->lessThanOrEqualTo($start)) {
                    $duration = 0;
                } else {
                    $endReference = $endTime;

                    if ($pass->type === 'privatni') {
                        try {
                            $workdayEnd = Carbon::parse($start->toDateString() . ' ' . $workdayEndString);
                        } catch (\Throwable $e) {
                            $workdayEnd = $start->copy()->setTime(15, 0);
                        }

                        if ($workdayEnd->greaterThan($start) && $endTime->greaterThan($workdayEnd)) {
                            $endReference = $workdayEnd;
                        }
                    }

                    $duration = max($start->diffInMinutes($endReference, false), 0);
                }

                if ((int) $pass->duration_minutes !== (int) $duration) {
                    $changed++;
                    if (!$dryRun) {
                        $pass->update(['duration_minutes' => $duration]);
                    }
                }
            }
        });

        $this->info('Processed passes: ' . $total);
        $this->info(($dryRun ? 'Would change: ' : 'Changed: ') . $changed);

        return Command::SUCCESS;
    }
}

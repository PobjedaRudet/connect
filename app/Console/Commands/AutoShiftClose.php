<?php

namespace App\Console\Commands;

use App\Services\AutoShiftCloseService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AutoShiftClose extends Command
{
    protected $signature = 'app:auto-shift-close {shift : I, II ili III}';

    protected $description = 'Automatski odjavljuje radnike smjene koji su zaboravili odjavu na terminalu ili kapiji.';

    public function handle(AutoShiftCloseService $service): int
    {
        $shift = strtoupper(trim((string) $this->argument('shift')));
        if (!in_array($shift, ['I', 'II', 'III'], true)) {
            $this->error('Smjena mora biti I, II ili III.');

            return self::FAILURE;
        }

        try {
            $result = $service->close($shift);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Zatvoreno prijava na terminalu: ' . $result['closed_terminal']);
        $this->info('Zatvoreno izlazaka na kapiji: ' . $result['closed_gate']);
        $this->info('Zatvoreno izlaznica: ' . $result['closed_passes']);
        $this->info('Mailova spremljeno za 06:30: ' . $result['mails_queued']);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\AttendanceMismatchService;
use App\Services\AutoShiftCloseService;
use Illuminate\Console\Command;

class SendPendingAttendanceMails extends Command
{
    protected $signature = 'app:send-pending-attendance-mails';

    protected $description = 'Šalje odložene mailove automatske odjave i neslaganja dolaska.';

    public function handle(AutoShiftCloseService $autoShiftClose, AttendanceMismatchService $mismatches): int
    {
        $shiftMails = $autoShiftClose->sendPendingMails();
        $mismatchMails = $mismatches->sendPendingMails();

        $this->info('Mailova automatske odjave: ' . $shiftMails);
        $this->info('Mailova neslaganja: ' . $mismatchMails);

        return self::SUCCESS;
    }
}

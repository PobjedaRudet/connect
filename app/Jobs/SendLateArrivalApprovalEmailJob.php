<?php

namespace App\Jobs;

use App\Services\AttendanceService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Runs after the HTTP response (dispatch()->afterResponse()).
 * Intentionally NOT queued — QUEUE_CONNECTION=database would otherwise leave
 * the email sitting in `jobs` until a queue worker picks it up.
 */
class SendLateArrivalApprovalEmailJob
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $employeeId,
        public int $passId,
    ) {}

    public function handle(AttendanceService $service): void
    {
        $service->deliverLateArrivalApprovalEmail($this->employeeId, $this->passId);
    }
}

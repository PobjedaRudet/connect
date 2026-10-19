<?php

namespace App\Jobs;

use App\Mail\AttendanceMismatchMail;
use App\Models\AttendanceMismatchNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAttendanceMismatchMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<int>  $notificationIds
     */
    public function __construct(
        public int $userId,
        public string $periodLabel,
        public array $notificationIds,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $email = trim((string) ($user?->email ?? ''));

        if ($email === '') {
            Log::warning('Mail o neslaganjima nije poslan: nadležni nema email.', [
                'user_id' => $this->userId,
                'notification_ids' => $this->notificationIds,
            ]);

            return;
        }

        $notifications = AttendanceMismatchNotification::query()
            ->with('employee:id,firstName,lastName')
            ->whereIn('id', $this->notificationIds)
            ->whereNotIn('type', ['pass_open', 'pass_missing', 'pass_time_mismatch'])
            ->orderBy('work_date')
            ->orderBy('employee_id')
            ->get();

        if ($notifications->isEmpty()) {
            return;
        }

        Mail::to($email)->send(new AttendanceMismatchMail($this->periodLabel, $notifications));
    }
}

<?php

namespace App\Jobs;

use App\Mail\UpcomingExamsMail;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendExamNotifications implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $today = Carbon::today();
        $nextWeek = $today->copy()->addDays(7);

        $employees = Employee::whereHas('pregledi')
            ->with(['pregledi' => fn ($q) => $q->orderByDesc('datum_pregleda')->limit(1)])
            ->get();

        $upcoming = [];
        $expired = [];

        foreach ($employees as $employee) {
            $lastExam = $employee->pregledi->first();
            if (!$lastExam || !$employee->period) continue;

            $nextDue = Carbon::parse($lastExam->datum_pregleda)->addMonths((int) $employee->period);

            if ($nextDue->between($today, $nextWeek)) {
                $upcoming[] = ['employee' => $employee, 'next_due' => $nextDue];
            } elseif ($nextDue->lessThan($today)) {
                $expired[] = ['employee' => $employee, 'next_due' => $nextDue];
            }
        }

        if (count($upcoming) || count($expired)) {
            Mail::to('h.ahmet@pobjeda.com')->send(new UpcomingExamsMail($upcoming, $expired));
        }

        \Illuminate\Support\Facades\Log::info('Mail poslan- JOB.');
    }
}

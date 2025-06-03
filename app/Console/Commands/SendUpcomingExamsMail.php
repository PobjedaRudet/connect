<?php

namespace App\Console\Commands;

use App\Mail\UpcomingExamsMail;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendUpcomingExamsMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-upcoming-exams-mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pošalji mail o nadolazećim i isteklih ljekarskim pregledima';

    /**
     * Execute the console command.
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

        $this->info('Mail poslan.');
    }
}

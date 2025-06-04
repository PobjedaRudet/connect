<?php

namespace App\Console\Commands;

use App\Mail\NextMonthExamsMail;
use App\Mail\UpcomingExamsMail;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendExamsNextMonthMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-exams-next-month-mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pošalji mail o ljekarskim pregledima koji dolaze u narednom mjesecu';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Pokrenuta komanda za preglede idući mjesec - ' . now());
        $today = Carbon::today();
        $startNextMonth = $today->copy()->addMonthNoOverflow()->startOfMonth();
        $endNextMonth = $startNextMonth->copy()->endOfMonth();

        $employees = Employee::whereHas('pregledi')
            ->with(['pregledi' => fn ($q) => $q->orderByDesc('datum_pregleda')->limit(1)])
            ->get();

        $upcoming = [];
        $expired = [];

        foreach ($employees as $employee) {
            $lastExam = $employee->pregledi->first();
            if (!$lastExam || !$employee->period) continue;

            $nextDue = Carbon::parse($lastExam->datum_pregleda)->addMonths((int) $employee->period);

            if ($nextDue->between($startNextMonth, $endNextMonth)) {
                $upcoming[] = ['employee' => $employee, 'next_due' => $nextDue];
            } elseif ($nextDue->lessThan($today)) {
                $expired[] = ['employee' => $employee, 'next_due' => $nextDue];
            }
        }

        $recipients = [
            'h.ahmet@pobjeda.com',
            'z.neira@pobjeda.com',
            'a.salkovic@pobjeda.com',
            'k.asim@pobjeda.com',

            // Dodajte još email adresa po potrebi
        ];

        if (count($upcoming) || count($expired)) {
            Mail::to($recipients)->send(new NextMonthExamsMail($upcoming, $expired));
        }

        $this->info('Mail za idući mjesec poslan.');
    }
}

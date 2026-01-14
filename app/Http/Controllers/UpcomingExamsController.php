<?php

namespace App\Http\Controllers;

use App\Mail\UpcomingExamsMail;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Response;

class UpcomingExamsController extends Controller
{
    /**
     * Pošalji e-mail o predstojećim i isteklim pregledima.
     */
    public function send(): Response
    {
        $today = Carbon::today();
        $nextWeek = $today->copy()->addDays(7);

        $employees = Employee::whereHas('pregledi')
            ->with(['pregledi' => fn ($q) => $q->orderByDesc('datum_pregleda')->limit(10)])
            ->get();

        $upcoming = [];
        $expired = [];

        foreach ($employees as $employee) {
            $lastExam = $employee->pregledi->first();
            if (!$lastExam || !$employee->period) {
                continue;
            }

            $nextDue = Carbon::parse($lastExam->datum_pregleda)->addMonths((int) $employee->period);

            if ($nextDue->between($today, $nextWeek)) {
                $upcoming[] = ['employee' => $employee, 'next_due' => $nextDue];
            } elseif ($nextDue->lessThan($today)) {
                $expired[] = ['employee' => $employee, 'next_due' => $nextDue];
            }
        }

        $recipients = [
            'z.neira@pobjeda.com',
            'a.salkovic@pobjeda.com',
            'k.asim@pobjeda.com',
        ];

        Mail::to($recipients)->send(new UpcomingExamsMail($upcoming, $expired));

        return response('Mail poslan.');
    }
}

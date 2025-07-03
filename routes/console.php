<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-upcoming-exams-mail')
    ->monthlyOn(3, '07:54') // 1st day of the month at midnight
    ->description('Mjesečni izvještaj o nadolazećim i isteklih ljekarskim pregledima');

Schedule::command('app:send-exams-next-month-mail')
        ->monthlyOn(30, '12:57')
        ->description('Komanda za slanje maila za idući mjesec je pokrenuta.');



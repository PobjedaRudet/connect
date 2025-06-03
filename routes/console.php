<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-upcoming-exams-mail')
    ->monthlyOn(3, '11:31') // 1st day of the month at midnight
    ->description('Mjesečni izvještaj o nadolazećim i isteklih ljekarskim pregledima');


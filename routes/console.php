<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use App\Jobs\QueuePing;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-upcoming-exams-mail')
    ->monthlyOn(3, '07:54') // 1st day of the month at midnight
    ->description('Mjesečni izvještaj o nadolazećim i isteklih ljekarskim pregledima');

Schedule::command('app:send-exams-next-month-mail')
        ->monthlyOn(30, '12:57')
        ->description('Komanda za slanje maila za idući mjesec je pokrenuta.');

// Provjera otvorenih izlaznica svake minute
Schedule::command('app:close-stale-passes')
    ->everyMinute()
    ->description('Automatski zatvara otvorene izlaznice na kraj smjene.');


Artisan::command('app:doctor {--queue-ping} {--mail=}', function () {
    $out = [];

    // App env
    $out['app_env'] = (string) config('app.env');

    // DB check
    try {
        DB::select('select 1 as ok');
        $out['db'] = 'ok';
    } catch (\Throwable $e) {
        $out['db'] = 'fail: ' . $e->getMessage();
    }

    // Cache check
    try {
        Cache::put('health_check', 'ok', 60);
        $val = Cache::get('health_check');
        $out['cache'] = $val === 'ok' ? 'ok' : 'fail: unexpected value';
    } catch (\Throwable $e) {
        $out['cache'] = 'fail: ' . $e->getMessage();
    }

    // Queue basics
    $defaultConn = config('queue.default');
    $out['queue_default'] = $defaultConn;
    if ($defaultConn === 'database') {
        try {
            $table = config('queue.connections.database.table', 'jobs');
            $exists = Schema::hasTable($table);
            $pending = $exists ? DB::table($table)->count() : null;
            $out['queue_table'] = $exists ? 'ok' : 'missing';
            if ($exists) {
                $out['queue_pending_jobs'] = $pending;
            }
        } catch (\Throwable $e) {
            $out['queue_table'] = 'fail: ' . $e->getMessage();
        }
    }

    // Optional: queue ping
    if ($this->option('queue-ping')) {
        try {
            Cache::forget('queue_ping_at');
            dispatch(new QueuePing());
            sleep(3);
            $ts = Cache::get('queue_ping_at');
            $out['queue_ping'] = $ts ? ('ok at ' . $ts) : 'fail: no ping within 3s';
        } catch (\Throwable $e) {
            $out['queue_ping'] = 'fail: ' . $e->getMessage();
        }
    }

    // Optional: send test mail
    $email = (string) $this->option('mail');
    if ($email !== '') {
        try {
            Mail::raw('Test poruka iz app:doctor', function ($m) use ($email) {
                $m->to($email)->subject('Test mail (app:doctor)');
            });
            $out['mail'] = 'sent to ' . $email;
        } catch (\Throwable $e) {
            $out['mail'] = 'fail: ' . $e->getMessage();
        }
    }

    // Output summary
    foreach ($out as $k => $v) {
        $this->line(str_pad($k . ':', 22) . (is_scalar($v) ? $v : json_encode($v)));
    }
})->purpose('Brza dijagnostika (DB/Cache/Queue/Mail) nakon deploy-a');



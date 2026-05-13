<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== needs_backfill sample ===" . PHP_EOL;
$backfill = DB::table('employees as e')
    ->join('radna_mjesta as rm', 'rm.id', '=', 'e.radna_mjesta_id')
    ->where(function ($q) {
        $q->whereNull('e.radno_mjesto')
          ->orWhereRaw("TRIM(e.radno_mjesto) = ''");
    })
    ->select('e.id', 'e.firstName', 'e.lastName', 'rm.radno_mjesto as target')
    ->limit(10)
    ->get();

foreach ($backfill as $r) {
    echo "{$r->id} | {$r->firstName} {$r->lastName} | target={$r->target}" . PHP_EOL;
}

echo PHP_EOL . "=== mismatch sample ===" . PHP_EOL;
$mismatch = DB::table('employees as e')
    ->join('radna_mjesta as rm', 'rm.id', '=', 'e.radna_mjesta_id')
    ->whereRaw("TRIM(COALESCE(e.radno_mjesto, '')) <> TRIM(rm.radno_mjesto)")
    ->select('e.id', 'e.firstName', 'e.lastName', 'e.radno_mjesto as current', 'rm.radno_mjesto as target')
    ->limit(10)
    ->get();

foreach ($mismatch as $r) {
    echo "{$r->id} | {$r->firstName} {$r->lastName} | current={$r->current} | target={$r->target}" . PHP_EOL;
}

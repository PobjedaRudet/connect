<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total = DB::table('employees')->count();
$fkNotNull = DB::table('employees')->whereNotNull('radna_mjesta_id')->count();
$fkNull = DB::table('employees')->whereNull('radna_mjesta_id')->count();

$needsBackfill = DB::table('employees')
    ->whereNotNull('radna_mjesta_id')
    ->where(function ($q) {
        $q->whereNull('radno_mjesto')
          ->orWhereRaw("TRIM(radno_mjesto) = ''");
    })
    ->count();

$mismatch = DB::table('employees as e')
    ->join('radna_mjesta as rm', 'rm.id', '=', 'e.radna_mjesta_id')
    ->whereRaw("TRIM(COALESCE(e.radno_mjesto, '')) <> TRIM(rm.radno_mjesto)")
    ->count();

echo "total={$total}" . PHP_EOL;
echo "fk_not_null={$fkNotNull}" . PHP_EOL;
echo "fk_null={$fkNull}" . PHP_EOL;
echo "needs_backfill={$needsBackfill}" . PHP_EOL;
echo "mismatch={$mismatch}" . PHP_EOL;

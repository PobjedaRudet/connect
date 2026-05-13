<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hasColumn = Schema::hasColumn('employees', 'radna_mjesta_id') ? 'yes' : 'no';
$total = DB::table('employees')->count();
$emptyRadnoMjesto = DB::table('employees')
    ->where(function ($q) {
        $q->whereNull('radno_mjesto')->orWhereRaw("TRIM(radno_mjesto) = ''");
    })
    ->count();

$textLinkable = DB::table('employees as e')
    ->join('radna_mjesta as rm', DB::raw('TRIM(e.radno_mjesto)'), '=', DB::raw('TRIM(rm.radno_mjesto)'))
    ->count();

echo "has_radna_mjesta_id_column={$hasColumn}" . PHP_EOL;
echo "employees_total={$total}" . PHP_EOL;
echo "employees_empty_radno_mjesto={$emptyRadnoMjesto}" . PHP_EOL;
echo "employees_text_linkable_to_radna_mjesta={$textLinkable}" . PHP_EOL;

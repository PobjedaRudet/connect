<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== RADNA MJESTA ===" . PHP_EOL;
$rms = DB::table('radna_mjesta')->select('id', 'sifra', 'radno_mjesto')->get();
foreach ($rms as $r) {
    echo $r->id . " | " . $r->sifra . " | " . $r->radno_mjesto . PHP_EOL;
}

echo PHP_EOL . "=== EMPLOYEES radno_mjesto samples ===" . PHP_EOL;
$emps = DB::table('employees')->select('empID', 'firstName', 'lastName', 'radno_mjesto')->limit(20)->get();
foreach ($emps as $e) {
    echo $e->empID . " | " . $e->firstName . " " . $e->lastName . " | " . $e->radno_mjesto . PHP_EOL;
}

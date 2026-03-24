<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$empValues = App\Models\Employee::whereNotNull('radno_mjesto')->pluck('radno_mjesto')->unique()->sort()->values();
$rmValues = Illuminate\Support\Facades\DB::table('radna_mjesta')->pluck('radno_mjesto')->unique()->sort()->values();
$missing = $empValues->diff($rmValues);
echo 'Employees radna mjesta: ' . $empValues->count() . PHP_EOL;
echo 'Radna_mjesta records: ' . $rmValues->count() . PHP_EOL;
echo 'Missing in radna_mjesta: ' . $missing->count() . PHP_EOL;
if ($missing->isNotEmpty()) {
    foreach ($missing as $m) echo '  - ' . $m . PHP_EOL;
}

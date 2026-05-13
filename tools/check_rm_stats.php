<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total = DB::table('employees')->count();
$filled = DB::table('employees')->whereNotNull('radna_mjesta_id')->count();
$null   = DB::table('employees')->whereNull('radna_mjesta_id')->count();

echo "Ukupno uposlenika: $total" . PHP_EOL;
echo "radna_mjesta_id popunjeno: $filled" . PHP_EOL;
echo "radna_mjesta_id NULL: $null" . PHP_EOL;

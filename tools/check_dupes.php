<?php
require 'vendor/autoload.php';
(require 'bootstrap/app.php')->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dupes = Illuminate\Support\Facades\DB::table('radna_mjesta')
    ->select('radno_mjesto', Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
    ->groupBy('radno_mjesto')
    ->having('cnt', '>', 1)
    ->get();

foreach ($dupes as $d) {
    echo $d->radno_mjesto . ' (' . $d->cnt . 'x)' . PHP_EOL;
}
echo 'Total duplicate groups: ' . $dupes->count() . PHP_EOL;

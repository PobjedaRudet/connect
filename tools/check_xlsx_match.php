<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:/Users/h.ahmet/Desktop/sifre_i_uposlenici.xlsx';
$spreadsheet = IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

// Build sifra => radna_mjesta.id map
$radnaMjesta = DB::table('radna_mjesta')->select('id', 'sifra')->get()->keyBy('sifra');

$matched = 0;
$notMatched = [];

foreach (array_slice($rows, 1) as $row) {
    $sifra = trim((string)$row[0]);
    $name  = trim((string)$row[1]);

    if (!$sifra || !$name) continue;

    // Parse name: "Prezime Ime" format
    $parts = explode(' ', $name, 2);
    $lastName  = strtoupper($parts[0]);
    $firstName = strtoupper($parts[1] ?? '');

    // Find in radna_mjesta
    $rm = $radnaMjesta->get($sifra);
    if (!$rm) {
        $notMatched[] = "Sifra not found in radna_mjesta: $sifra → $name";
        continue;
    }

    // Find employee
    $emp = DB::table('employees')
        ->whereRaw("UPPER(lastName) = ?", [$lastName])
        ->whereRaw("UPPER(firstName) = ?", [$firstName])
        ->first();

    if (!$emp) {
        // try reversed (some files have "Ime Prezime")
        $emp = DB::table('employees')
            ->whereRaw("UPPER(lastName) = ?", [strtoupper($parts[1] ?? '')])
            ->whereRaw("UPPER(firstName) = ?", [strtoupper($parts[0])])
            ->first();
    }

    if ($emp) {
        $matched++;
        // echo "OK: $name → sifra=$sifra rmID={$rm->id} empID={$emp->empID}" . PHP_EOL;
    } else {
        $notMatched[] = "Employee not found: $name (sifra=$sifra)";
    }
}

echo "Matched: $matched" . PHP_EOL;
echo "Not matched: " . count($notMatched) . PHP_EOL;
foreach ($notMatched as $m) {
    echo "  - $m" . PHP_EOL;
}

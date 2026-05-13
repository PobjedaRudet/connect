<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:/Users/h.ahmet/Desktop/sifre_i_uposlenici.xlsx';
$spreadsheet = IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

echo "Total rows: " . count($rows) . PHP_EOL;
echo str_repeat('-', 120) . PHP_EOL;

// Print first 15 rows
foreach (array_slice($rows, 0, 15) as $i => $row) {
    echo "Row $i: ";
    foreach ($row as $j => $cell) {
        echo "[$j]=" . str_pad((string)$cell, 25);
    }
    echo PHP_EOL;
}

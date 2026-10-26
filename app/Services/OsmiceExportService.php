<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OsmiceExportService
{
    private const MONTH_CODES = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Maj',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Aug',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Dec',
    ];

    private const FIRST_DAY_COLUMN = 5; // E = dan 1

    private const LAST_DAY_COLUMN = 35; // AI = dan 31

    public function hasTemplate(int $departmentId): bool
    {
        return is_file($this->templatePath($departmentId));
    }

    public function storeTemplate(int $departmentId, UploadedFile $file): void
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            throw new \RuntimeException('Fajl se ne može otvoriti. Uploadujte .xlsx Osmice template.');
        }

        $sheet = $spreadsheet->getSheetByName('Osmice Final') ?? $spreadsheet->getActiveSheet();
        if ($this->slotRows($sheet) === []) {
            $spreadsheet->disconnectWorksheets();
            throw new \RuntimeException('Fajl nije Osmice template. Nedostaju redovi za radnike (Red.).');
        }
        $spreadsheet->disconnectWorksheets();

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || $contents === '') {
            throw new \RuntimeException('Fajl se ne može sačuvati.');
        }
        Storage::disk('local')->put($this->relativePath($departmentId), $contents);
    }

    public function download(array $data, int $departmentId, string $orgUnit, string $search = ''): StreamedResponse
    {
        $month = Carbon::createFromFormat('Y-m', (string) $data['month'])->startOfMonth();
        $employees = $this->filterEmployees($data['employees'] ?? [], $search);
        $employees = array_values(array_filter(
            $employees,
            fn (array $employee): bool => (int) ($employee['department_id'] ?? 0) === $departmentId
        ));
        $spreadsheet = $this->fill($data, $employees, $month, $orgUnit, $departmentId);
        $filename = $this->filename($month, $orgUnit);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     */
    public function fill(array $data, array $employees, Carbon $month, string $orgUnit, int $departmentId): Spreadsheet
    {
        $path = $this->templatePath($departmentId);
        if (!is_file($path)) {
            throw new \RuntimeException('Nema template-a za odabrani odjel.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Osmice Final') ?? $spreadsheet->getActiveSheet();

        $this->writeHeader($sheet, $month, $orgUnit);
        $this->ensureCapacity($sheet, count($employees));
        foreach ($this->slotRows($sheet) as $row) {
            $this->clearSlot($sheet, $row);
        }
        $this->writeEmployees($sheet, $employees, $data['attendance'] ?? [], $month);
        $this->dropUnused($sheet, count($employees));

        return $spreadsheet;
    }

    private function relativePath(int $departmentId): string
    {
        return 'osmice-templates/' . $departmentId . '.xlsx';
    }

    private function templatePath(int $departmentId): string
    {
        return Storage::disk('local')->path($this->relativePath($departmentId));
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     * @return list<array<string, mixed>>
     */
    private function filterEmployees(array $employees, string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return array_values($employees);
        }

        $needle = mb_strtolower($search);

        return array_values(array_filter($employees, function (array $employee) use ($needle): bool {
            return str_contains(mb_strtolower((string) ($employee['full_name'] ?? '')), $needle);
        }));
    }

    private function writeHeader(Worksheet $sheet, Carbon $month, string $orgUnit): void
    {
        $sheet->setCellValue('C9', self::MONTH_CODES[(int) $month->month]);
        $sheet->setCellValue('D9', (int) $month->year);

        $datum = 'Datum:   ' . $month->copy()->endOfMonth()->format('d.m.Y') . '.';
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $current = $sheet->getCell('A' . $row)->getValue();
            if (is_string($current) && str_starts_with($current, 'Datum')) {
                $sheet->setCellValue('A' . $row, $datum);
            }
        }

        if ($orgUnit !== '') {
            $sheet->setCellValue('F7', $orgUnit);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     * @param  array<int|string, array<string, mixed>>  $attendance
     */
    private function writeEmployees(Worksheet $sheet, array $employees, array $attendance, Carbon $month): void
    {
        $daysInMonth = $month->daysInMonth;

        $slots = $this->slotRows($sheet);

        foreach ($employees as $index => $employee) {
            $row = $slots[$index] ?? null;
            if ($row === null) {
                break;
            }
            $name = mb_strtoupper(trim((string) ($employee['full_name'] ?? '')), 'UTF-8');
            if ($name !== '') {
                $sheet->setCellValue('B' . $row, $name);
            }

            $employeeId = (int) ($employee['id'] ?? 0);
            $days = $attendance[$employeeId] ?? $attendance[(string) $employeeId] ?? [];

            for ($day = 1; $day <= $daysInMonth && $day <= 31; $day++) {
                $date = $month->copy()->day($day)->toDateString();
                $cell = $days[$date] ?? null;
                if (!is_array($cell)) {
                    continue;
                }

                $value = $this->cellValue($cell);
                if ($value === null || $value === '') {
                    continue;
                }

                $coordinate = $sheet->getCell([$this->dayColumn($day), $row])->getCoordinate();
                $this->writeDayValue($sheet, $coordinate, $value);
            }
        }
    }

    private function dayColumn(int $day): int
    {
        return self::FIRST_DAY_COLUMN + $day - 1;
    }

    /**
     * Isti prikaz kao na šihterici: šifra (GO, BO, II, III, 8) ili broj sati.
     */
    private function cellValue(array $cell): int|float|string|null
    {
        $display = $cell['duration_display'] ?? null;
        if (is_string($display) && trim($display) !== '') {
            return $this->normalizeDisplay(trim($display));
        }

        if (!isset($cell['duration_minutes']) || $cell['duration_minutes'] === null) {
            return null;
        }

        $rounded = round(((int) $cell['duration_minutes']) / 60, 1);

        return $this->normalizeNumber($rounded);
    }

    private function normalizeDisplay(string $display): int|float|string
    {
        $numeric = str_replace(',', '.', $display);
        if (is_numeric($numeric)) {
            return $this->normalizeNumber((float) $numeric);
        }

        return $display;
    }

    private function normalizeNumber(float $hours): int|float
    {
        $whole = round($hours);
        if (abs($hours - $whole) < 0.001) {
            return (int) $whole;
        }

        return $hours;
    }

    private function writeDayValue(Worksheet $sheet, string $coordinate, int|float|string $value): void
    {
        if (is_int($value) || is_float($value)) {
            $sheet->setCellValue($coordinate, $value);
            if (is_float($value)) {
                $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('0.0');
            }

            return;
        }

        $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
    }

    /**
     * @return list<int>
     */
    private function slotRows(Worksheet $sheet): array
    {
        $rows = [];
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($sheet->getCell('D' . $row)->getValue() === 'Red.') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function pageRanges(Worksheet $sheet): array
    {
        $legendRows = [];
        $highest = $sheet->getHighestRow();
        for ($row = 1; $row <= $highest; $row++) {
            $value = $sheet->getCell('A' . $row)->getValue();
            if (is_string($value) && str_starts_with($value, 'Legenda')) {
                $legendRows[] = $row;
            }
        }

        if ($legendRows === []) {
            return [[1, $highest]];
        }

        $pages = [];
        $start = 1;
        foreach ($legendRows as $legend) {
            $pages[] = [$start, $legend];
            $start = $legend + 1;
        }

        if ($pages !== [] && $start <= $highest) {
            $pages[count($pages) - 1][1] = $highest;
        }

        return $pages;
    }

    private function clearSlot(Worksheet $sheet, int $row): void
    {
        $sheet->setCellValue('B' . $row, null);
        for ($col = self::FIRST_DAY_COLUMN; $col <= self::LAST_DAY_COLUMN; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($letter . $row, null);
            $sheet->setCellValue($letter . ($row + 1), null);
        }
    }

    private function ensureCapacity(Worksheet $sheet, int $employeeCount): void
    {
        if ($employeeCount === 0) {
            return;
        }

        while (count($this->slotRows($sheet)) < $employeeCount) {
            $before = count($this->slotRows($sheet));
            $this->appendPageCopy($sheet);
            if (count($this->slotRows($sheet)) <= $before) {
                throw new \RuntimeException('Template nema dovoljno redova za sve radnike.');
            }
        }
    }

    private function appendPageCopy(Worksheet $sheet): void
    {
        $pages = $this->pageRanges($sheet);
        [$sourceStart, $highest] = $pages[array_key_last($pages)];
        $offset = $highest - $sourceStart + 1;
        $merges = $sheet->getMergeCells();
        $mergeAnchors = [];
        foreach ($merges as $merge) {
            [$start] = explode(':', $merge);
            $mergeAnchors[$start] = true;
        }

        for ($row = $sourceStart; $row <= $highest; $row++) {
            $destRow = $row + $offset;
            $height = $sheet->getRowDimension($row)->getRowHeight();
            if ($height > 0) {
                $sheet->getRowDimension($destRow)->setRowHeight($height);
            }

            for ($col = 1; $col <= self::LAST_DAY_COLUMN + 28; $col++) {
                $sourceCoordinate = Coordinate::stringFromColumnIndex($col) . $row;
                $source = $sheet->getCell($sourceCoordinate);
                $merge = $source->getMergeRange();
                if (is_string($merge) && !isset($mergeAnchors[$sourceCoordinate])) {
                    continue;
                }

                $destCoordinate = Coordinate::stringFromColumnIndex($col) . $destRow;
                $value = $source->getValue();
                if (is_string($value) && str_starts_with($value, '=')) {
                    $value = $this->shiftFormula($value, $sourceStart, $highest, $offset);
                }
                $sheet->setCellValue($destCoordinate, $value);
                $sheet->duplicateStyle($sheet->getStyle($sourceCoordinate), $destCoordinate);
            }
        }

        foreach ($merges as $merge) {
            [$start, $end] = explode(':', $merge);
            [$startColumn, $startRow] = Coordinate::coordinateFromString($start);
            [$endColumn, $endRow] = Coordinate::coordinateFromString($end);
            $startRow = (int) $startRow;
            $endRow = (int) $endRow;
            if ($startRow < $sourceStart || $endRow > $highest) {
                continue;
            }

            $sheet->mergeCells(
                $startColumn . ($startRow + $offset) . ':' . $endColumn . ($endRow + $offset)
            );
        }
    }

    private function shiftFormula(string $formula, int $sourceStart, int $sourceEnd, int $offset): string
    {
        $shifted = preg_replace_callback(
            '/(\$?)([A-Z]{1,3})(\$?)(\d+)/',
            function (array $match) use ($sourceStart, $sourceEnd, $offset): string {
                $row = (int) $match[4];
                if ($match[3] === '$' || $row < $sourceStart || $row > $sourceEnd) {
                    return $match[0];
                }

                return $match[1] . $match[2] . $match[3] . ($row + $offset);
            },
            $formula
        );

        return is_string($shifted) ? $shifted : $formula;
    }

    private function dropUnused(Worksheet $sheet, int $employeeCount): void
    {
        $pages = $this->pageRanges($sheet);
        $slotsPerPage = 1;
        if ($pages !== []) {
            [$start, $end] = $pages[0];
            $onFirstPage = 0;
            foreach ($this->slotRows($sheet) as $row) {
                if ($row >= $start && $row <= $end) {
                    $onFirstPage++;
                }
            }
            $slotsPerPage = max(1, $onFirstPage);
        }

        $pagesNeeded = $employeeCount === 0 ? 1 : (int) ceil($employeeCount / $slotsPerPage);
        if (count($pages) > $pagesNeeded) {
            $cutFrom = $pages[$pagesNeeded][0];
            $highest = $sheet->getHighestRow();
            if ($highest >= $cutFrom) {
                $this->removeSheetRows($sheet, $cutFrom, $highest - $cutFrom + 1);
            }
        }

        $slots = $this->slotRows($sheet);
        if ($employeeCount < count($slots) && $slots !== []) {
            $firstUnused = $slots[$employeeCount];
            $lastSlot = $slots[count($slots) - 1];
            $this->removeSheetRows($sheet, $firstUnused, ($lastSlot + 1) - $firstUnused + 1);
        }

        $this->trimTrailingRows($sheet);
    }

    private function removeSheetRows(Worksheet $sheet, int $row, int $count): void
    {
        if ($count < 1) {
            return;
        }

        $helper = ReferenceHelper::getInstance();
        $property = new ReflectionProperty($helper, 'cellReferenceHelper');
        $property->setValue($helper, null);
        $sheet->removeRow($row, $count);
    }

    private function trimTrailingRows(Worksheet $sheet): void
    {
        $highest = max($sheet->getHighestDataRow(), 1);
        $last = 1;
        for ($row = $highest; $row >= 1; $row--) {
            $rowHasValue = false;
            for ($col = 1; $col <= self::LAST_DAY_COLUMN + 28; $col++) {
                $value = $sheet->getCell([$col, $row])->getValue();
                if ($value !== null && $value !== '') {
                    $rowHasValue = true;
                    break;
                }
            }
            if ($rowHasValue) {
                $last = $row;
                break;
            }
        }

        $property = new ReflectionProperty($sheet, 'rowDimensions');
        $dimensions = $property->getValue($sheet);
        if (is_array($dimensions)) {
            foreach (array_keys($dimensions) as $index) {
                if ((int) $index > $last) {
                    unset($dimensions[$index]);
                }
            }
            $property->setValue($sheet, $dimensions);
        }

        foreach ($sheet->getMergeCells() as $merge) {
            [, $end] = explode(':', $merge);
            [, $endRow] = Coordinate::coordinateFromString($end);
            if ((int) $endRow <= $last) {
                continue;
            }
            $sheet->unmergeCells($merge);
        }

        $collection = $sheet->getCellCollection();
        for ($row = $last + 1; $row <= $highest; $row++) {
            $collection->removeRow($row);
        }

        $sheet->garbageCollect();
    }

    private function filename(Carbon $month, string $orgUnit): string
    {
        $monthCode = strtolower(self::MONTH_CODES[(int) $month->month]);
        $label = $this->filenameLabel($orgUnit);
        $prefix = $label !== '' ? ('Osmice ' . $label . '_') : 'Osmice_';

        return $prefix . $monthCode . '_' . $month->year . '.xlsx';
    }

    private function filenameLabel(string $orgUnit): string
    {
        $label = trim($orgUnit);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/[\\\\\\/:*?"<>|]+/u', ' ', $label) ?? $label;
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;

        return trim($label);
    }
}

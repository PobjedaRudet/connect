<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RadnaMjestaIdSeeder extends Seeder
{
    public function run(): void
    {
        $path = $this->resolveWorkbookPath();

        if (!$path) {
            $this->command->warn('Excel fajl nije pronadjen. Ocekivano: tools/sifre_i_uposlenici_source.xlsx');
            return;
        }

        $rows = IOFactory::load($path)->getActiveSheet()->toArray();

        $radnaMjestaBySifra = DB::table('radna_mjesta')
            ->select('id', 'sifra', 'radno_mjesto')
            ->get()
            ->keyBy(fn ($row) => $this->normalizeCode($row->sifra));

        $employeeIndex = $this->buildEmployeeIndex();

        $updated = 0;
        $unchanged = 0;
        $missingSifra = [];
        $missingEmployee = [];
        $ambiguousEmployee = [];

        DB::transaction(function () use (
            $rows,
            $radnaMjestaBySifra,
            $employeeIndex,
            &$updated,
            &$unchanged,
            &$missingSifra,
            &$missingEmployee,
            &$ambiguousEmployee
        ) {
            foreach (array_slice($rows, 1) as $row) {
                $sifra = $this->normalizeCode($row[0] ?? '');
                $fullName = trim((string) ($row[1] ?? ''));

                if ($sifra === '' || $fullName === '') {
                    continue;
                }

                $radnoMjesto = $radnaMjestaBySifra->get($sifra);

                if (!$radnoMjesto) {
                    $missingSifra[] = "{$sifra} -> {$fullName}";
                    continue;
                }

                $matches = $this->findEmployees($employeeIndex, $fullName);

                if ($matches->count() > 1) {
                    $ambiguousEmployee[] = "{$fullName} (sifra={$sifra})";
                    continue;
                }

                $employee = $matches->first();

                if (!$employee) {
                    $missingEmployee[] = "{$fullName} (sifra={$sifra})";
                    continue;
                }

                if (trim((string) $employee->radno_mjesto) === trim((string) $radnoMjesto->radno_mjesto)) {
                    $unchanged++;
                    continue;
                }

                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'radno_mjesto' => $radnoMjesto->radno_mjesto,
                        'updated_at' => now(),
                    ]);

                $updated++;
            }
        });

        $this->command->info("Povezivanje zavrseno: azurirano={$updated}, vec ispravno={$unchanged}");
        $this->report('Sifre koje ne postoje u radna_mjesta', $missingSifra);
        $this->report('Uposlenici koji nisu pronadjeni', $missingEmployee);
        $this->report('Dvosmislena poklapanja uposlenika', $ambiguousEmployee);
    }

    private function resolveWorkbookPath(): ?string
    {
        $candidates = [
            base_path('tools/sifre_i_uposlenici_source.xlsx'),
            'C:/Users/h.ahmet/Desktop/sifre_i_uposlenici.xlsx',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildEmployeeIndex()
    {
        $index = collect();

        DB::table('employees')
            ->select('id', 'firstName', 'lastName', 'middleName', 'radno_mjesto')
            ->orderBy('id')
            ->get()
            ->each(function ($employee) use ($index) {
                $names = [
                    "{$employee->lastName} {$employee->firstName}",
                    "{$employee->firstName} {$employee->lastName}",
                    "{$employee->lastName} {$employee->middleName} {$employee->firstName}",
                    "{$employee->firstName} {$employee->middleName} {$employee->lastName}",
                ];

                foreach ($names as $name) {
                    foreach ($this->nameKeys($name) as $key) {
                        $index->put($key, $index->get($key, collect())->push($employee));
                    }
                }
            });

        return $index;
    }

    private function findEmployees($employeeIndex, string $fullName)
    {
        foreach ($this->nameKeys($fullName) as $key) {
            $matches = $employeeIndex->get($key, collect())->unique('id')->values();

            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        return collect();
    }

    private function nameKeys(string $name): array
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique([
            $normalized,
            str_replace(' ', '', $normalized),
        ]));
    }

    private function normalizeName(string $name): string
    {
        $name = strtr($name, [
            'č' => 'c', 'ć' => 'c', 'đ' => 'dj', 'š' => 's', 'ž' => 'z',
            'Č' => 'c', 'Ć' => 'c', 'Đ' => 'dj', 'Š' => 's', 'Ž' => 'z',
        ]);

        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? '';

        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    private function normalizeCode(mixed $value): string
    {
        return trim((string) $value);
    }

    private function report(string $title, array $items): void
    {
        if (!$items) {
            $this->command->info("{$title}: 0");
            return;
        }

        $this->command->warn("{$title}: " . count($items));

        foreach ($items as $item) {
            $this->command->line("  - {$item}");
        }
    }
}

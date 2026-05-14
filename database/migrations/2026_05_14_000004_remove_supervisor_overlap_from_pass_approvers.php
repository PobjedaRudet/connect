<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->select(['id', 'nadlezne_osobe', 'pass_approvers'])
            ->orderBy('id')
            ->chunkById(100, function ($employees): void {
                foreach ($employees as $employee) {
                    $supervisors = $this->normalizeIds($employee->nadlezne_osobe);
                    $passApprovers = $this->normalizeIds($employee->pass_approvers);

                    if (empty($supervisors) || empty($passApprovers)) {
                        continue;
                    }

                    $filteredPassApprovers = array_values(array_diff($passApprovers, $supervisors));
                    if ($filteredPassApprovers === $passApprovers) {
                        continue;
                    }

                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update([
                            'pass_approvers' => empty($filteredPassApprovers)
                                ? null
                                : json_encode($filteredPassApprovers, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible cleanup: overlapping pass approvers are intentionally not restored.
    }

    private function normalizeIds(mixed $raw): array
    {
        if (is_int($raw)) {
            $raw = [$raw];
        }

        if (is_string($raw)) {
            if (ctype_digit($raw)) {
                $raw = [(int) $raw];
            } else {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_int($value) || is_string($value)) {
                $value = (int) $value;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        }

        return array_values(array_unique($ids));
    }
};

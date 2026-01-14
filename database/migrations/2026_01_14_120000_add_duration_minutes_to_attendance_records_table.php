<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->integer('duration_minutes')->nullable()->after('exit_time');
        });

        // Backfill existing rows (MySQL/MariaDB): compute minutes between start and end.
        // Prefer effective_start if present, otherwise entry_time.
        try {
            DB::statement(
                "UPDATE attendance_records\n".
                "SET duration_minutes = GREATEST(\n".
                "    TIMESTAMPDIFF(MINUTE, COALESCE(effective_start, entry_time), exit_time),\n".
                "    0\n".
                ")\n".
                "WHERE exit_time IS NOT NULL\n".
                "  AND COALESCE(effective_start, entry_time) IS NOT NULL"
            );
        } catch (\Throwable $e) {
            // If DB engine doesn't support TIMESTAMPDIFF, keep duration_minutes null; it will be computed on next save.
        }
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};

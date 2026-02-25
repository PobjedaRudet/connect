<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_leave_decisions', function (Blueprint $table) {
            if (!Schema::hasColumn('annual_leave_decisions', 'part')) {
                $table->enum('part', ['ljetni', 'zimski', 'jednodnevni', 'ostalo'])
                    ->default('ostalo')
                    ->after('year');
            }

            // Ensure employee_id has its own index so dropping the old UNIQUE index
            // won't break the FK requirements in MySQL.
            try {
                $table->index('employee_id', 'annual_leave_decisions_employee_id_index');
            } catch (\Throwable $e) {
                // ignore if index already exists
            }

            // Allow multiple decisions per employee per year (summer/winter/one-day).
            // Drop the old unique constraint if it exists.
            try {
                $table->dropUnique('annual_leave_decisions_employee_year_unique');
            } catch (\Throwable $e) {
                // ignore if it does not exist or DB doesn't support it
            }

            try {
                $table->index(['employee_id', 'year', 'part'], 'annual_leave_decisions_employee_year_part_index');
            } catch (\Throwable $e) {
                // ignore if index already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('annual_leave_decisions', function (Blueprint $table) {
            try {
                $table->dropIndex('annual_leave_decisions_employee_year_part_index');
            } catch (\Throwable $e) {
                // ignore
            }

            if (Schema::hasColumn('annual_leave_decisions', 'part')) {
                $table->dropColumn('part');
            }

            // Best-effort restore of original uniqueness.
            try {
                $table->unique(['employee_id', 'year'], 'annual_leave_decisions_employee_year_unique');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};

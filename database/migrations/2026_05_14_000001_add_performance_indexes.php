<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // attendance_records: svaki scan traži otvoreni record (exit_time IS NULL)
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['employee_id', 'exit_time'], 'idx_ar_employee_exit');
        });

        // passes: lookup otvorenog pass-a (status='open', end_time IS NULL)
        Schema::table('passes', function (Blueprint $table) {
            $table->index(['employee_id', 'status', 'end_time'], 'idx_passes_emp_status_end');
            // lookup nedavno zatvorenog pass-a u findRecentlyClosedPass()
            $table->index(['employee_id', 'end_time'], 'idx_passes_emp_end');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_ar_employee_exit');
        });

        Schema::table('passes', function (Blueprint $table) {
            $table->dropIndex('idx_passes_emp_status_end');
            $table->dropIndex('idx_passes_emp_end');
        });
    }
};

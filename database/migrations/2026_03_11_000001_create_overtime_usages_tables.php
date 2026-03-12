<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('overtime_usages')) {
            Schema::create('overtime_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('usage_date');
                $table->integer('minutes_used');
                $table->string('usage_type', 50);
                $table->text('note')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'usage_date']);
                $table->index('usage_type');
            });
        }

        if (!Schema::hasTable('overtime_usage_allocations')) {
            Schema::create('overtime_usage_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('overtime_usage_id')->constrained('overtime_usages')->cascadeOnDelete();
                $table->foreignId('attendance_overtime_id')->constrained('attendance_overtimes')->cascadeOnDelete();
                $table->integer('minutes_allocated');
                $table->timestamps();

                $table->unique(['overtime_usage_id', 'attendance_overtime_id'], 'overtime_usage_alloc_unique');
                $table->index(['attendance_overtime_id', 'minutes_allocated'], 'overtime_usage_alloc_overtime_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_usage_allocations');
        Schema::dropIfExists('overtime_usages');
    }
};

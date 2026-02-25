<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('annual_leave_usages')) {
            return;
        }

        Schema::create('annual_leave_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annual_leave_decision_id')
                ->constrained('annual_leave_decisions')
                ->cascadeOnDelete();

            // Optional link to the original request (existing leaves table).
            $table->unsignedBigInteger('leave_id')->nullable();

            $table->date('date_from');
            $table->date('date_to');

            // Deducted days (store the value you actually want to deduct for auditability).
            $table->decimal('days', 5, 2);

            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'date_from']);
            $table->index('annual_leave_decision_id');
            $table->index('leave_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_leave_usages');
    }
};

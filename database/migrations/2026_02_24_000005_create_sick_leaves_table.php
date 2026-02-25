<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_leaves', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Optional link to a generic leave request (leaves.type=bolovanje).
            // No FK on purpose (keeps migrations robust across environments).
            $table->unsignedBigInteger('leave_id')->nullable();

            $table->date('from');
            $table->date('to');

            // Whole days (no decimals). Can be filled manually or computed later.
            $table->unsignedSmallInteger('days')->nullable();

            $table->string('document_number', 50)->nullable();
            $table->date('document_date')->nullable();

            $table->string('doctor', 150)->nullable();
            $table->string('diagnosis_code', 50)->nullable();

            $table->text('note')->nullable();
            $table->string('attachment_path', 2048)->nullable();

            $table->enum('status', ['otvoreno', 'zatvoreno'])->default('otvoreno');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'from']);
            $table->index('leave_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_leaves');
    }
};

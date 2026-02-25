<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_leave_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            $table->string('decision_number', 50)->nullable();
            $table->date('decision_date')->nullable();

            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Total granted days for that year (optionally includes carry-over).
            $table->decimal('granted_days', 5, 2)->default(0);
            $table->decimal('carried_over_days', 5, 2)->default(0);

            $table->text('note')->nullable();
            $table->string('attachment_path', 2048)->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['employee_id', 'year'], 'annual_leave_decisions_employee_year_unique');
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_leave_decisions');
    }
};

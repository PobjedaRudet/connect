<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_service_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Where the service was accrued (external employer, internal department, etc.).
            $table->string('employer_name')->nullable();
            $table->string('position')->nullable();

            $table->enum('service_type', ['internal', 'external', 'military', 'education', 'other'])->default('external');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Whether this period counts towards official seniority (radni staž).
            $table->boolean('is_recognized')->default(true);

            $table->string('document_number', 100)->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_service_periods');
    }
};

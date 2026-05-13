<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['privatni','službeni']);
            $table->text('reason');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('approved')->default(false);
            $table->enum('status', ['open','closed'])->default('open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passes');
    }
};

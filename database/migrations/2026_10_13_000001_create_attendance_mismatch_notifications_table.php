<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_mismatch_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('type', 64);
            $table->text('description');
            $table->timestamp('gate_in_at')->nullable();
            $table->timestamp('gate_out_at')->nullable();
            $table->timestamp('building_in_at')->nullable();
            $table->timestamp('building_out_at')->nullable();
            $table->timestamp('pass_start_at')->nullable();
            $table->timestamp('pass_end_at')->nullable();
            $table->boolean('mail_sent')->default(false);
            $table->json('notified_user_ids')->nullable();
            $table->string('mail_skipped_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date', 'type'], 'attendance_mismatch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_mismatch_notifications');
    }
};

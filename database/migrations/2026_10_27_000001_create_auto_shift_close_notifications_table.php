<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_shift_close_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('shift_code', 8);
            $table->timestamp('cutoff_at');
            $table->string('missing');
            $table->boolean('mail_sent')->default(false);
            $table->json('notified_user_ids')->nullable();
            $table->string('mail_skipped_reason')->nullable();
            $table->timestamps();

            $table->index(['mail_sent', 'mail_skipped_reason'], 'auto_shift_close_mail_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_shift_close_notifications');
    }
};

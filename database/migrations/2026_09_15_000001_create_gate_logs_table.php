<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * gate_logs: nezavisna evidencija ulaza/izlaza sa kapije (okretna vrata na ulazu u firmu).
     * Namjerno odvojeno od attendance_records — kapija ne utiče na obračun radnog vremena,
     * već služi kao kontrola (uporediva sa prijavom/odjavom na terminalu kod objekta).
     */
    public function up(): void
    {
        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->timestamp('scanned_at');
            $table->string('terminal_id')->nullable();
            $table->string('rfid_code')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'scanned_at'], 'idx_gate_logs_employee_scanned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_logs');
    }
};

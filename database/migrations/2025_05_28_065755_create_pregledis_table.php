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
        Schema::create('pregledis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('datum_pregleda');
            $table->enum('type', ['Sposoban', 'Ograničen', 'Nesposoban']);
            $table->boolean('kontrolni_pregled')->nullable()->comment('Kontrolni pregled nakon 3-6 meseci');
            $table->text('komentar')->nullable();
            $table->text('organizacija')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pregledis');
    }
};

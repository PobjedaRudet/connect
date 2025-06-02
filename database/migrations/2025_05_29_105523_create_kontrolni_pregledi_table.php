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
        Schema::create('kontrolni_pregledi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregledi_id')->constrained('pregledis');
            $table->date('datum_kontrolnog_pregleda')->nullable();
            $table->text('kontrolni_komentar')->nullable();
            $table->boolean('status')->default(true)->comment('Status kontrolnog pregleda true ako je aktivan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontrolni_pregledi');
    }
};

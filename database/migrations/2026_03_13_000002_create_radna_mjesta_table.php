<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radna_mjesta', function (Blueprint $table) {
            $table->id();
            $table->string('sifra');
            $table->string('radno_mjesto');
            $table->string('strucna_sprema');
            $table->string('smjer');
            $table->integer('broj');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radna_mjesta');
    }
};

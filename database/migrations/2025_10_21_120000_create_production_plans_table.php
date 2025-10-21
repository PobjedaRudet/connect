<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->string('objekat'); // Laboracija I smjena , Laboracija II smjena, Kompletiranje, Kompletiranje Nonel
            $table->date('laboracija_datum')->nullable();
            $table->foreignId('planned_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plans');
    }
};

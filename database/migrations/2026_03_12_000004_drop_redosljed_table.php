<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('redosljed');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('redosljed')) {
            return;
        }

        Schema::create('redosljed', function (Blueprint $table) {
            $table->id();
            $table->string('radno_mjesto');
            $table->float('redni_broj');
            $table->timestamps();
        });
    }
};
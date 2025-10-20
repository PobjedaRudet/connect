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
        if (!Schema::hasTable('approvals')) {
            Schema::create('approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('UserId')->nullable();
                $table->string('Funkcija', 50);
                $table->boolean('Odobreno');
                $table->dateTime('DatumOdobravanja');
                $table->string('Komentar', 255);
                $table->timestamps();
                $table->foreign('UserId')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('Funkcija')->references('Funkcija')->on('funkcije')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};

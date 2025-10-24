<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('route_name')->unique();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('funkcija_page')) {
            Schema::create('funkcija_page', function (Blueprint $table) {
                $table->unsignedBigInteger('page_id');
                $table->string('funkcija'); // references funkcije.Funkcija (string PK)
                $table->primary(['page_id','funkcija']);
                $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
                $table->foreign('funkcija')->references('Funkcija')->on('funkcije')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('funkcija_page');
        Schema::dropIfExists('pages');
    }
};

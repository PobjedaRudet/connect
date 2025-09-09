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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
             $table->string('OrderNumber');
            $table->date('OrderDate');
            $table->text('Description')->nullable();
            $table->string('Status');
            $table->string('BojaDuzinaProvodnika')->nullable();
            $table->string('Pakovanje')->nullable();
            $table->string('Tip')->nullable();
            $table->string('AtestPaketa')->nullable();
            $table->string('CeOznaka')->nullable();
            $table->string('KlasaOpasnosti')->nullable();
            $table->string('UNBroj')->nullable();
            $table->string('VrstaProvodnika')->nullable();
            $table->string('Metraza')->nullable();
            $table->string('RokIsporuke')->nullable();
            $table->date('DatumPredaje')->nullable();
            $table->date('DatumPrijema')->nullable();
            $table->string('token')->unique()->nullable();
            $table->string('Napomena')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};

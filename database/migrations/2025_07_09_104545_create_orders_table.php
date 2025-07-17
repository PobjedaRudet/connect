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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('OrderNumber');
            $table->date('OrderDate');
            $table->text('Description');
            $table->string('Status');
            $table->string('CurrentEmployee');
            $table->string('BojaDuzinaProvodnika');
            $table->string('Pakovanje');
            $table->string('AtestPaketa');
            $table->string('CeOznaka');
            $table->string('KlasaOpasnosti');
            $table->string('UNBroj');
            $table->string('RokIsporuke');
            $table->string('DatumPredaje');
            $table->string('DatumPrijema');
            $table->string('Napomena');

            $table->unsignedBigInteger('ProizvodId');
            $table->foreign('ProizvodId')->references('id')->on('products')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

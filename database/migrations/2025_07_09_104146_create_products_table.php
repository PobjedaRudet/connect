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
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('Naziv');
                $table->string('Tip');
                $table->string('SkraceniNaziv');
                $table->string('JedinicaMjere');
                $table->string('Code');
                $table->string('UoM_meter')->nullable();
                $table->Integer('UsporenjeMs')->nullable();
                $table->Integer('UNNumber')->nullable();
                $table->string('HazardClass')->nullable();
                $table->string('CEMarkNumber')->nullable();
                $table->Integer('NumeraProizvoda')->nullable();
                $table->string('VrstaProvodnika')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};


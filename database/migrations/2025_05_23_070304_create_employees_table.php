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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empID')->unique();
            $table->string('lastName', 100);
            $table->string('firstName', 100);
            $table->string('middleName', 100)->nullable();
            $table->integer('period')->nullable();
            $table->boolean('rizik')->nullable();
            $table->string('radno_mjesto')->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('jobTitle', 100)->nullable();
            $table->string('dept', 100)->nullable();
            $table->string('email', 100)->unique()->nullable();
            $table->date('startDate')->nullable();
            $table->string('status', 50)->nullable(); //Ugovor na neodređeno=1, određeno=2
            $table->date('termDate')->nullable();
            $table->string('termReason', 255)->nullable();
            $table->string('homeStreet', 255)->nullable();
            $table->string('homeZip', 20)->nullable();
            $table->string('homeCity', 100)->nullable();
            $table->string('homeCounty', 100)->nullable();
            $table->string('homeCountr', 100)->nullable();
            $table->string('homeState', 100)->nullable();
            $table->date('birthDate')->nullable();
            $table->string('brthCountr', 100)->nullable();
            $table->string('martStatus', 50)->nullable()->comment('Marital status');
            $table->integer('nChildren')->nullable();
            $table->string('govID', 50)->nullable()->comment('Government ID');
            $table->string('picture', 255)->nullable()->comment('Path to employee picture');
            $table->string('position', 100)->nullable();
            $table->boolean('Active')->default(true); // AU radnom odnosu, trenutno zaposlen
            $table->string('profesionalno_oboljenje', 100)->nullable();
            $table->string('invalidnost_radnika', 100)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

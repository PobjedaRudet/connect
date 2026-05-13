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
            $table->string('rfid_code', 50)->nullable()->unique();
            $table->json('nadlezne_osobe')->nullable();
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
            $table->string('status', 50)->nullable();
            $table->string('homeCounty', 100)->nullable();
            $table->boolean('Active')->default(true);
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

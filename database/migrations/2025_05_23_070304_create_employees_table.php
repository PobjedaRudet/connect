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
            $table->string('first_name'); // Ime zaposlenog
            $table->string('last_name'); // Prezime zaposlenog
            $table->string('department'); // Odeljenje
            $table->string('function'); // Funkcija
            $table->string('mobile_number'); // Broj mobilnog telefona
            $table->string('status'); // Status (npr. "aktivno", "neaktivno")
            $table->text('desc')->nullable(); // Opis (može biti prazan)
            $table->string('education'); // Obrazovanje
            $table->string('email', 250)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('code');
            $table->integer('level');
            $table->rememberToken();
            $table->date('date_of_birth')->nullable(); // Datum rođenja
            $table->string('address')->nullable(); // Adresa
            $table->string('city')->nullable(); // Grad
            $table->string('country')->nullable(); // Država
            $table->string('postal_code')->nullable(); // Poštanski broj
            $table->boolean('active'); // Da li je uposlenik aktivan
            $table->string('image')->nullable(); // Slika
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

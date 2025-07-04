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
        Schema::create('radnici_po_redosljedu', function (Blueprint $table) {
            $table->id(); // auto increment primary key
            $table->string('prezime');
            $table->string('ime');
            $table->string('radno_mjesto');
            $table->integer('redni_broj');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radnici_po_redosljedu');
    }
};

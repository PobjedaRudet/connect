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
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('radna_mjesta_id')->nullable()->after('radno_mjesto');
            $table->foreign('radna_mjesta_id')->references('id')->on('radna_mjesta')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['radna_mjesta_id']);
            $table->dropColumn('radna_mjesta_id');
        });
    }
};

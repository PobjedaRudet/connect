<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            // Marks this pass as auto-created from an early departure check-out
            $table->boolean('early_departure')->default(false)->after('late_minutes');
            // How many minutes the employee left early
            $table->unsignedSmallInteger('early_minutes')->nullable()->after('early_departure');
        });
    }

    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropColumn(['early_departure', 'early_minutes']);
        });
    }
};

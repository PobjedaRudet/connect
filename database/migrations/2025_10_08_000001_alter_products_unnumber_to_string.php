<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                // Change UNNumber from integer to string (VARCHAR(16))
                $table->string('UNNumber', 16)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                // Revert back to integer if rolled back
                $table->integer('UNNumber')->nullable()->change();
            });
        }
    }
};

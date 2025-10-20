<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'funkcija')) {
                $table->string('funkcija', 50)->nullable()->after('email');
                $table->foreign('funkcija')->references('Funkcija')->on('funkcije')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'funkcija')) {
                try { $table->dropForeign(['funkcija']); } catch (\Throwable $e) { /* ignore */ }
                $table->dropColumn('funkcija');
            }
        });
    }
};

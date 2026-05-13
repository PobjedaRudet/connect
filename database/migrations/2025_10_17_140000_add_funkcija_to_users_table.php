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
            if (!Schema::hasColumn('users', 'isadmin')) {
                $table->boolean('isadmin')->default(false)->after('funkcija');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'isadmin')) {
                $table->dropColumn('isadmin');
            }
            if (Schema::hasColumn('users', 'funkcija')) {
                try { $table->dropForeign(['funkcija']); } catch (\Throwable $e) {}
                $table->dropColumn('funkcija');
            }
        });
    }
};

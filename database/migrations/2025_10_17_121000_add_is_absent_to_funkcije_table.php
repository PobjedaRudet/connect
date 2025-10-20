<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funkcije', function (Blueprint $table) {
            if (!Schema::hasColumn('funkcije', 'is_absent')) {
                $table->boolean('is_absent')->default(false)->after('Redosljed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('funkcije', function (Blueprint $table) {
            if (Schema::hasColumn('funkcije', 'is_absent')) {
                $table->dropColumn('is_absent');
            }
        });
    }
};

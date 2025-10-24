<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('percent')->default(100)->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            $table->dropColumn('percent');
        });
    }
};

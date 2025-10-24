<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            $table->string('placeholder_label')->nullable()->after('percent');
            $table->string('placeholder_partner')->nullable()->after('placeholder_label');
        });
    }

    public function down(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            $table->dropColumn(['placeholder_label','placeholder_partner']);
        });
    }
};

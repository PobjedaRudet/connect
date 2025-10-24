<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            $table->unsignedBigInteger('production_order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            // Warning: this will fail if nulls exist. Ensure data is cleaned before rollback.
            $table->unsignedBigInteger('production_order_id')->nullable(false)->change();
        });
    }
};

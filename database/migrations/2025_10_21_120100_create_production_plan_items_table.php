<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained('production_plans')->onDelete('cascade');
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('delivery_date')->nullable();
            $table->unsignedTinyInteger('percent')->default(100);
            $table->string('placeholder_label')->nullable();
            $table->string('placeholder_partner')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan_items');
    }
};

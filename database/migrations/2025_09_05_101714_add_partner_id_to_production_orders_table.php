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
        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('dodatno', 250)->nullable()->after('DatumPrijema');
            $table->boolean('is_void')->default(false)->after('Status');
            $table->timestamp('voided_at')->nullable()->after('is_void');
            $table->string('void_reason', 500)->nullable()->after('voided_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropColumn(['partner_id', 'dodatno', 'is_void', 'voided_at', 'void_reason']);
        });
    }
};

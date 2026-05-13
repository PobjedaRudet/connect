<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->after('id');
            $table->foreign('order_id')->references('id')->on('production_orders')->onDelete('cascade');
            $table->boolean('signed_by_proxy')->default(false)->after('Komentar');
        });

        // Make columns nullable
        DB::statement('ALTER TABLE approvals MODIFY DatumOdobravanja DATETIME NULL');
        DB::statement('ALTER TABLE approvals MODIFY Komentar VARCHAR(255) NULL');
        DB::statement('ALTER TABLE approvals MODIFY Odobreno TINYINT(1) NULL');
        DB::statement('ALTER TABLE approvals MODIFY UserId BIGINT UNSIGNED NULL');

        // Unique index per order and function
        try {
            Schema::table('approvals', function (Blueprint $table) {
                $table->unique(['order_id', 'Funkcija'], 'approvals_order_funkcija_unique');
            });
        } catch (\Throwable $e) {
            // ignore if exists
        }
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['order_id', 'signed_by_proxy']);
            try { $table->dropUnique('approvals_order_funkcija_unique'); } catch (\Throwable $e) {}
        });
    }
};

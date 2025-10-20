<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            if (!Schema::hasColumn('approvals', 'signed_by_proxy')) {
                $table->boolean('signed_by_proxy')->default(false)->after('Komentar');
            }
        });

        // Make DatumOdobravanja and Komentar nullable using raw SQL to avoid requiring doctrine/dbal
        try {
            DB::statement('ALTER TABLE approvals MODIFY DatumOdobravanja DATETIME NULL');
        } catch (\Throwable $e) {
            // ignore if already nullable or DB does not support this exact syntax
        }
        try {
            DB::statement('ALTER TABLE approvals MODIFY Komentar VARCHAR(255) NULL');
        } catch (\Throwable $e) {
            // ignore
        }

        // Make Odobreno nullable to represent pending state
        try {
            DB::statement('ALTER TABLE approvals MODIFY Odobreno TINYINT(1) NULL');
        } catch (\Throwable $e) {
            // ignore
        }
        // Ensure unique per order and function
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS approvals_order_funkcija_unique ON approvals (order_id, Funkcija)');
        } catch (\Throwable $e) {
            // Fallback for MySQL which does not support IF NOT EXISTS on indexes prior to 8.0
            try {
                Schema::table('approvals', function (Blueprint $table) {
                    $table->unique(['order_id', 'Funkcija'], 'approvals_order_funkcija_unique');
                });
            } catch (\Throwable $e2) {
                // ignore if exists
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            if (Schema::hasColumn('approvals', 'signed_by_proxy')) {
                $table->dropColumn('signed_by_proxy');
            }
            try {
                $table->dropUnique('approvals_order_funkcija_unique');
            } catch (\Throwable $e) {
                // ignore
            }
        });

        // Optionally revert nullable changes (skip to avoid destructive operations)
    }
};

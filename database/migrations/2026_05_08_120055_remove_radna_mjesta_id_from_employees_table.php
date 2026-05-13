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
        // Preserve current FK mappings into employees.radno_mjesto before dropping the FK column.
        DB::statement("\n            UPDATE employees e\n            JOIN radna_mjesta rm ON rm.id = e.radna_mjesta_id\n            SET e.radno_mjesto = rm.radno_mjesto\n            WHERE e.radna_mjesta_id IS NOT NULL\n        ");

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['radna_mjesta_id']);
            $table->dropColumn('radna_mjesta_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('radna_mjesta_id')->nullable()->after('radno_mjesto');
            $table->foreign('radna_mjesta_id')->references('id')->on('radna_mjesta')->nullOnDelete();
        });

        // Best-effort restore based on textual radno_mjesto.
        DB::statement("\n            UPDATE employees e\n            JOIN radna_mjesta rm ON TRIM(e.radno_mjesto) = TRIM(rm.radno_mjesto)\n            SET e.radna_mjesta_id = rm.id\n            WHERE e.radna_mjesta_id IS NULL\n        ");
    }
};

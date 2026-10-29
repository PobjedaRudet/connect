<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Postojeća kolona je enum('in','out','',''). MySQL 8 pri ALTER TABLE
        // prepisuje tabelu i odbija tu definiciju (error 1291). Vrijednosti su samo in/out.
        $column = DB::selectOne("SHOW COLUMNS FROM gate_logs WHERE Field = 'direction'");
        $type = strtolower((string) ($column->Type ?? ''));

        if (str_contains($type, "''")) {
            DB::statement("ALTER TABLE gate_logs MODIFY direction ENUM('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        }

        Schema::table('gate_logs', function (Blueprint $table) {
            $table->boolean('offline')->default(false)->after('terminal_id');
        });
    }

    public function down(): void
    {
        Schema::table('gate_logs', function (Blueprint $table) {
            $table->dropColumn('offline');
        });
    }
};

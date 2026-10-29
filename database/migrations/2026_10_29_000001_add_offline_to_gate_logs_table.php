<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

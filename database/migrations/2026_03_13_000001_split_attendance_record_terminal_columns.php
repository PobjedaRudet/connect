<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('terminal_in')->nullable()->after('late_flag');
            $table->string('terminal_out')->nullable()->after('terminal_in');
        });

        DB::table('attendance_records')
            ->whereNotNull('terminal_id')
            ->update([
                'terminal_in' => DB::raw('terminal_id'),
                'terminal_out' => DB::raw('terminal_id'),
            ]);

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('terminal_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('terminal_id')->nullable()->after('late_flag');
        });

        DB::table('attendance_records')
            ->update([
                'terminal_id' => DB::raw('COALESCE(terminal_out, terminal_in)'),
            ]);

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['terminal_in', 'terminal_out']);
        });
    }
};

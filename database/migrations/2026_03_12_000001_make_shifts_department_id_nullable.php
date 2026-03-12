<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $fallbackDepartmentId = DB::table('departments')->min('id');

        if ($fallbackDepartmentId !== null) {
            DB::table('shifts')
                ->whereNull('department_id')
                ->update(['department_id' => $fallbackDepartmentId]);
        }

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable(false)->change();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter([
            'homeCountr',
            'homeState',
            'position',
        ], fn ($column) => Schema::hasColumn('employees', $column)));

        if ($columns === []) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'homeCountr')) {
                $table->string('homeCountr', 100)->nullable();
            }
            if (!Schema::hasColumn('employees', 'homeState')) {
                $table->string('homeState', 100)->nullable();
            }
            if (!Schema::hasColumn('employees', 'position')) {
                $table->string('position', 100)->nullable();
            }
        });
    }
};

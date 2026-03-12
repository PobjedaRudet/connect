<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter([
            'startDate',
            'termDate',
            'termReason',
            'homeStreet',
            'homeZip',
            'homeCity',
            'homeCountr',
            'birthDate',
            'brthCountr',
            'martStatus',
            'nChildren',
            'govID',
            'picture',
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
            $table->date('startDate')->nullable();
            $table->date('termDate')->nullable();
            $table->string('termReason', 255)->nullable();
            $table->string('homeStreet', 255)->nullable();
            $table->string('homeZip', 20)->nullable();
            $table->string('homeCity', 100)->nullable();
            $table->string('homeCountr', 100)->nullable();
            $table->date('birthDate')->nullable();
            $table->string('brthCountr', 100)->nullable();
            $table->string('martStatus', 50)->nullable()->comment('Marital status');
            $table->integer('nChildren')->nullable();
            $table->string('govID', 50)->nullable()->comment('Government ID');
            $table->string('picture', 255)->nullable()->comment('Path to employee picture');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ukloni radno_mjesto iz employees ako ne postoji u radna_mjesta
        DB::table('employees')
            ->whereNotNull('radno_mjesto')
            ->whereNotIn('radno_mjesto', DB::table('radna_mjesta')->pluck('radno_mjesto'))
            ->update(['radno_mjesto' => null]);

        // Ukloni duplikate iz radna_mjesta — zadrži samo red sa najmanjim id-om
        $dupes = DB::table('radna_mjesta')
            ->select('radno_mjesto', DB::raw('MIN(id) as keep_id'))
            ->groupBy('radno_mjesto')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($dupes as $d) {
            DB::table('radna_mjesta')
                ->where('radno_mjesto', $d->radno_mjesto)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        // Ukloni prazne zapise iz radna_mjesta
        DB::table('radna_mjesta')
            ->where('radno_mjesto', '')
            ->delete();

        // Dodaj unique index na radna_mjesta.radno_mjesto da može biti FK referenca
        Schema::table('radna_mjesta', function (Blueprint $table) {
            $table->unique('radno_mjesto');
        });

        // Dodaj FK na employees.radno_mjesto → radna_mjesta.radno_mjesto
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('radno_mjesto')
                ->references('radno_mjesto')
                ->on('radna_mjesta')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['radno_mjesto']);
        });

        Schema::table('radna_mjesta', function (Blueprint $table) {
            $table->dropUnique(['radno_mjesto']);
        });
    }
};

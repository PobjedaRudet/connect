<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            // Marks this pass as auto-generated from a late check-in
            $table->boolean('late_pass')->default(false)->after('approved_by_user_id');
            // How many minutes late the employee actually was
            $table->unsignedSmallInteger('late_minutes')->nullable()->after('late_pass');
        });
    }

    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropColumn(['late_pass', 'late_minutes']);
        });
    }
};

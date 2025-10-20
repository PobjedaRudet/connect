<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make UserId nullable for pending approvals
        try {
            DB::statement('ALTER TABLE approvals MODIFY UserId BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // ignore if already nullable or syntax differs per DB version
        }
    }

    public function down(): void
    {
        // Reverting to NOT NULL could break data; skip destructive revert
    }
};

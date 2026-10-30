<?php

use App\Models\AttendanceRecord;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AttendanceRecord::query()
            ->with(['shift', 'employee.department'])
            ->whereNotNull('exit_time')
            ->orderBy('id')
            ->chunkById(200, function ($records): void {
                foreach ($records as $record) {
                    $record->recalculateStoredDuration();
                }
            });
    }

    public function down(): void
    {
        // Preračun trajanja se ne vraća na stare minute.
    }
};

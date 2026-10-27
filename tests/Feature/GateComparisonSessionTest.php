<?php

namespace Tests\Feature;

use App\Services\GateComparisonService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GateComparisonSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/Sarajevo']);
        date_default_timezone_set('Europe/Sarajevo');

        Schema::dropIfExists('gate_logs');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empID')->nullable();
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->timestamp('entry_time')->nullable();
            $table->timestamp('exit_time')->nullable();
            $table->string('status')->default('working');
            $table->timestamps();
        });

        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('direction');
            $table->timestamp('scanned_at');
            $table->string('terminal_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_overnight_exit_stays_with_the_entry_day(): void
    {
        $employeeId = DB::table('employees')->insertGetId([
            'empID' => 240,
            'firstName' => 'Nedžib',
            'lastName' => 'Prutina',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_records')->insert([
            [
                'employee_id' => $employeeId,
                'entry_time' => '2026-10-26 07:00:00',
                'exit_time' => '2026-10-27 03:00:00',
                'status' => 'left',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_id' => $employeeId,
                'entry_time' => '2026-10-27 06:58:53',
                'exit_time' => null,
                'status' => 'working',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('gate_logs')->insert([
            $this->gate($employeeId, 'in', '2026-10-26 06:56:59'),
            $this->gate($employeeId, 'out', '2026-10-27 03:00:00'),
            $this->gate($employeeId, 'in', '2026-10-27 06:58:09'),
        ]);

        $service = app(GateComparisonService::class);

        $previous = $service->buildDailyReport('2026-10-26', 15, [$employeeId])['rows'][0];
        $this->assertSame('06:56', $previous['gate_in']);
        $this->assertSame('03:00 (27.10.)', $previous['gate_out']);
        $this->assertSame('07:00', $previous['building_in']);
        $this->assertSame('03:00 (27.10.)', $previous['building_out']);
        $this->assertSame('2026-10-27 03:00:00', $previous['gate_out_at']);
        $this->assertSame('2026-10-27 03:00:00', $previous['building_out_at']);

        $today = $service->buildDailyReport('2026-10-27', 15, [$employeeId])['rows'][0];
        $this->assertSame('06:58', $today['gate_in']);
        $this->assertNull($today['gate_out']);
        $this->assertSame('06:58', $today['building_in']);
        $this->assertNull($today['building_out']);
        $this->assertNotContains('03:00 (27.10.)', [$today['gate_out'], $today['building_out']]);
    }

    public function test_same_day_exit_is_unchanged_and_partial_window_does_not_look_ahead(): void
    {
        $employeeId = DB::table('employees')->insertGetId([
            'empID' => 1,
            'firstName' => 'Ana',
            'lastName' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_records')->insert([
            'employee_id' => $employeeId,
            'entry_time' => '2026-10-26 07:00:00',
            'exit_time' => '2026-10-26 15:30:00',
            'status' => 'left',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('gate_logs')->insert([
            $this->gate($employeeId, 'in', '2026-10-26 06:57:00'),
            $this->gate($employeeId, 'out', '2026-10-26 15:31:00'),
        ]);

        $row = app(GateComparisonService::class)->buildDailyReport('2026-10-26', 15, [$employeeId])['rows'][0];
        $this->assertSame('06:57', $row['gate_in']);
        $this->assertSame('15:31', $row['gate_out']);
        $this->assertSame('07:00', $row['building_in']);
        $this->assertSame('15:30', $row['building_out']);
        $this->assertFalse($row['flagged']);

        $partial = app(GateComparisonService::class)->buildDailyReport(
            '2026-10-26',
            15,
            [$employeeId],
            Carbon::parse('2026-10-26 08:00:00', 'Europe/Sarajevo'),
        )['rows'][0];
        $this->assertSame('06:57', $partial['gate_in']);
        $this->assertNull($partial['gate_out']);
        $this->assertNull($partial['building_out']);
    }

    private function gate(int $employeeId, string $direction, string $at): array
    {
        return [
            'employee_id' => $employeeId,
            'direction' => $direction,
            'scanned_at' => $at,
            'terminal_id' => 'KAPIJA',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

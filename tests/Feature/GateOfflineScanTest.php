<?php

namespace Tests\Feature;

use App\Models\GateLog;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GateOfflineScanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/Sarajevo']);
        date_default_timezone_set('Europe/Sarajevo');

        Schema::dropIfExists('gate_logs');
        Schema::dropIfExists('passes');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('rfid_code')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->timestamp('exit_time')->nullable();
            $table->timestamps();
        });

        Schema::create('passes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('status')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamps();
        });

        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('direction');
            $table->timestamp('scanned_at');
            $table->string('terminal_id')->nullable();
            $table->boolean('offline')->default(false);
            $table->string('rfid_code')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_offline_scan_stores_terminal_time_instead_of_receipt_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Europe/Sarajevo'));
        $employeeId = $this->employee();

        $response = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:17:05',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'status' => 'gate-in',
            'message' => 'Ulaz registrovan (offline)',
            'employee_id' => $employeeId,
            'employee_full_name' => 'Ana Test',
            'direction' => 'in',
            'scanned_at' => '2026-10-22 09:17:05',
        ]);
        $this->assertArrayNotHasKey('http_status', $response->json());

        $log = GateLog::query()->first();
        $this->assertSame('2026-10-22 09:17:05', $log->scanned_at->toDateTimeString());
        $this->assertTrue($log->offline);
        $this->assertNotSame('2026-10-22 12:00:00', $log->scanned_at->toDateTimeString());
    }

    public function test_offline_scan_accepts_timestamp_with_microseconds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Europe/Sarajevo'));
        $this->employee();

        $response = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:17:05.628844',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('scanned_at', '2026-10-22 09:17:05');
        $this->assertSame(
            '2026-10-22 09:17:05',
            GateLog::query()->first()->scanned_at->toDateTimeString(),
        );
    }

    public function test_offline_direction_uses_logs_at_or_before_scan_and_leaves_newer_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Europe/Sarajevo'));

        $onlyNewer = $this->employee('NEWER01');
        DB::table('gate_logs')->insert(
            $this->gate($onlyNewer, 'in', '2026-10-22 10:00:00', 'KAPIJA'),
        );

        $olderThanNewerIn = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'NEWER01',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:00:00',
        ]);
        $olderThanNewerIn->assertCreated();
        $olderThanNewerIn->assertJsonPath('direction', 'in');
        $this->assertSame('in', $this->logAt($onlyNewer, '2026-10-22 10:00:00')->direction);
        $this->assertFalse($this->logAt($onlyNewer, '2026-10-22 10:00:00')->offline);

        $between = $this->employee('BETWEEN');
        DB::table('gate_logs')->insert([
            $this->gate($between, 'in', '2026-10-22 08:00:00', 'KAPIJA'),
            $this->gate($between, 'out', '2026-10-22 11:00:00', 'KAPIJA'),
        ]);

        $afterEarlierIn = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'BETWEEN',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:00:00',
        ]);
        $afterEarlierIn->assertCreated();
        $afterEarlierIn->assertJsonPath('direction', 'out');
        $afterEarlierIn->assertJsonPath('message', 'Izlaz registrovan (offline)');
        $this->assertSame('in', $this->logAt($between, '2026-10-22 08:00:00')->direction);
        $this->assertSame('out', $this->logAt($between, '2026-10-22 11:00:00')->direction);
        $this->assertFalse($this->logAt($between, '2026-10-22 11:00:00')->offline);

        $afterAutoOut = $this->employee('AUTOOUT');
        DB::table('gate_logs')->insert([
            $this->gate($afterAutoOut, 'in', '2026-10-22 08:00:00', 'KAPIJA'),
            $this->gate($afterAutoOut, 'out', '2026-10-22 08:30:00', 'auto-shift-close'),
            $this->gate($afterAutoOut, 'in', '2026-10-22 10:00:00', 'KAPIJA'),
        ]);

        $afterAutomaticOut = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'AUTOOUT',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:00:00',
        ]);
        $afterAutomaticOut->assertCreated();
        $afterAutomaticOut->assertJsonPath('direction', 'in');
        $this->assertSame('in', $this->logAt($afterAutoOut, '2026-10-22 10:00:00')->direction);
    }

    public function test_repeated_offline_scan_returns_existing_log(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Europe/Sarajevo'));
        $this->employee();

        $payload = [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:17:05.628844',
        ];

        $first = $this->postJson('/api/gate-offline-scan', $payload);
        $first->assertCreated();

        $second = $this->postJson('/api/gate-offline-scan', $payload);
        $second->assertOk();
        $second->assertJson([
            'status' => 'gate-in',
            'message' => 'Ulaz registrovan (offline)',
            'direction' => 'in',
            'scanned_at' => '2026-10-22 09:17:05',
        ]);

        $sameSecond = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:17:05.100000',
        ]);
        $sameSecond->assertOk();

        $this->assertSame(1, GateLog::query()->count());
    }

    public function test_offline_exit_is_stored_while_building_checkout_is_open(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Europe/Sarajevo'));
        $employeeId = $this->employee();
        DB::table('gate_logs')->insert(
            $this->gate($employeeId, 'in', '2026-10-22 08:00:00', 'KAPIJA'),
        );
        DB::table('attendance_records')->insert([
            'employee_id' => $employeeId,
            'exit_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:00:00',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'status' => 'gate-out',
            'message' => 'Izlaz registrovan (offline)',
            'direction' => 'out',
            'scanned_at' => '2026-10-22 09:00:00',
        ]);
        $this->assertTrue(
            GateLog::query()->where('employee_id', $employeeId)->where('offline', true)->where('direction', 'out')->exists(),
        );
    }

    public function test_unknown_card_returns_404(): void
    {
        $response = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'NEMA',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 09:17:05',
        ]);

        $response->assertNotFound();
        $response->assertJson([
            'status' => 'error',
            'message' => 'Nepoznata kartica!',
        ]);
        $this->assertSame(0, GateLog::query()->count());
    }

    public function test_invalid_timestamp_returns_422(): void
    {
        $this->employee();

        $response = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
            'timestamp' => 'nije-datum',
        ]);

        $response->assertUnprocessable();
        $response->assertJson([
            'status' => 'error',
            'message' => 'Neispravan format vremena: nije-datum',
        ]);
        $this->assertSame(0, GateLog::query()->count());
    }

    public function test_timestamp_more_than_five_minutes_in_the_future_returns_422(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Europe/Sarajevo'));
        $this->employee();

        $response = $this->postJson('/api/gate-offline-scan', [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
            'timestamp' => '2026-10-22 12:06:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJson([
            'status' => 'error',
            'message' => 'Vrijeme skena je u budućnosti.',
        ]);
        $this->assertSame(0, GateLog::query()->count());
    }

    public function test_online_gate_scan_still_rejects_exit_without_building_checkout(): void
    {
        $employeeId = $this->employee();
        DB::table('gate_logs')->insert(
            $this->gate($employeeId, 'in', '2026-10-22 08:00:00', 'KAPIJA'),
        );
        DB::table('attendance_records')->insert([
            'employee_id' => $employeeId,
            'exit_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/gate-scan', [
            'rfid_code' => 'ANA001',
            'terminal_id' => 'KAPIJA',
        ]);

        $response->assertNotFound();
        $response->assertJson([
            'status' => 'error',
            'message' => 'Ne možete izaći na kapiji dok se ne odjavite na terminalu.',
            'employee_id' => $employeeId,
            'employee_full_name' => 'Ana Test',
        ]);
        $this->assertSame(1, GateLog::query()->count());
    }

    private function employee(string $rfid = 'ANA001'): int
    {
        return DB::table('employees')->insertGetId([
            'firstName' => 'Ana',
            'lastName' => 'Test',
            'rfid_code' => $rfid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function gate(int $employeeId, string $direction, string $at, string $terminalId): array
    {
        return [
            'employee_id' => $employeeId,
            'direction' => $direction,
            'scanned_at' => $at,
            'terminal_id' => $terminalId,
            'offline' => false,
            'rfid_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function logAt(int $employeeId, string $at): GateLog
    {
        return GateLog::query()
            ->where('employee_id', $employeeId)
            ->where('scanned_at', $at)
            ->firstOrFail();
    }
}

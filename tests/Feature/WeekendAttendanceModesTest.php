<?php

namespace Tests\Feature;

use App\Models\AttendanceOvertime;
use App\Models\AttendanceRecord;
use App\Models\AttendanceStimulation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WeekendAttendanceModesTest extends TestCase
{
    private int $empSeq = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        $this->createSchema();
    }

    public function test_regular_weekend_department_keeps_shift_end_overtime_and_ignores_stimulacija(): void
    {
        $department = $this->department(true);
        $employee = $this->employee($department, true);
        $shift = $this->shift($department);

        $withinGrace = $this->punch($employee, $shift, '2026-10-24 08:00:00', '2026-10-24 16:20:00');
        $this->assertSame(500, (int) $withinGrace->duration_minutes);
        $this->assertFalse($withinGrace->isBookedAsWeekendOvertime());
        $this->assertNoOvertimeOrStimulation($withinGrace);

        $afterShift = $this->punch($employee, $shift, '2026-10-24 08:00:00', '2026-10-24 17:00:00');
        $this->assertSame(540, (int) $afterShift->duration_minutes);
        $this->assertOnlyOvertime($afterShift, 60, '2026-10-24');
    }

    public function test_stimulacija_puts_the_whole_weekend_attendance_into_stimulation(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, true);
        $shift = $this->shift($department);

        $record = $this->punch($employee, $shift, '2026-10-25 08:00:00', '2026-10-25 12:00:00');

        $this->assertSame(240, (int) $record->duration_minutes);
        $this->assertFalse($record->isBookedAsWeekendOvertime());
        $this->assertOnlyStimulation($record, 240, '2026-10-25');
    }

    public function test_default_weekend_puts_the_whole_attendance_into_overtime(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, false);
        $shift = $this->shift($department);

        $record = $this->punch($employee, $shift, '2026-10-24 08:00:00', '2026-10-24 12:00:00');

        $this->assertSame(240, (int) $record->duration_minutes);
        $this->assertTrue($record->isBookedAsWeekendOvertime());
        $this->assertOnlyOvertime($record, 240, '2026-10-24');

        $sunday = $this->punch($employee, $shift, '2026-10-25 08:00:00', '2026-10-25 11:00:00');
        $this->assertTrue($sunday->isBookedAsWeekendOvertime());
        $this->assertOnlyOvertime($sunday, 180, '2026-10-25');

        $withoutDepartment = $this->employee(null, false);
        $openWeekend = $this->punch($withoutDepartment, $shift, '2026-10-24 09:00:00', '2026-10-24 11:00:00');
        $this->assertOnlyOvertime($openWeekend, 120, '2026-10-24');
    }

    public function test_weekday_overtime_stays_on_the_shift_end_rule(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, true);
        $shift = $this->shift($department);

        $withinGrace = $this->punch($employee, $shift, '2026-10-26 08:00:00', '2026-10-26 16:20:00');
        $this->assertSame(500, (int) $withinGrace->duration_minutes);
        $this->assertNoOvertimeOrStimulation($withinGrace);

        $afterShift = $this->punch($employee, $shift, '2026-10-26 08:00:00', '2026-10-26 17:00:00');
        $this->assertSame(540, (int) $afterShift->duration_minutes);
        $this->assertOnlyOvertime($afterShift, 60, '2026-10-26');
    }

    public function test_late_arrival_on_a_workday_counts_from_shift_start(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, false);
        $shift = $this->shift($department, '07:00:00', '15:00:00');

        $late = $this->punch($employee, $shift, '2026-10-26 08:00:00', '2026-10-26 15:00:00');

        $this->assertSame(480, (int) $late->duration_minutes);
        $this->assertNoOvertimeOrStimulation($late);

        $shift->attendance_credit_code = 'I';
        $shift->save();

        $capped = $this->punch($employee, $shift, '2026-10-26 08:00:00', '2026-10-26 16:01:00');
        $this->assertSame(480, (int) $capped->duration_minutes);
        $this->assertOnlyOvertime($capped, 61, '2026-10-26');

        $afterEnd = $this->punch($employee, $shift, '2026-10-26 16:30:00', '2026-10-26 18:00:00');
        $this->assertSame(90, (int) $afterEnd->duration_minutes);
    }

    public function test_late_weekend_that_is_not_regular_work_keeps_actual_presence(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, false);
        $shift = $this->shift($department, '07:00:00', '15:00:00');

        $record = $this->punch($employee, $shift, '2026-10-24 08:00:00', '2026-10-24 15:00:00');

        $this->assertSame(420, (int) $record->duration_minutes);
        $this->assertOnlyOvertime($record, 420, '2026-10-24');
    }

    public function test_late_arrival_on_regular_weekend_counts_from_shift_start(): void
    {
        $department = $this->department(true);
        $employee = $this->employee($department, false);
        $shift = $this->shift($department, '07:00:00', '15:00:00');

        $record = $this->punch($employee, $shift, '2026-10-24 08:00:00', '2026-10-24 15:00:00');

        $this->assertSame(480, (int) $record->duration_minutes);
        $this->assertNoOvertimeOrStimulation($record);
    }

    public function test_auto_close_late_arrival_still_credits_the_full_shift(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, false);
        $shift = $this->shift($department, '07:00:00', '15:00:00');

        $record = $this->punch($employee, $shift, '2026-10-26 08:00:00', '2026-10-26 23:00:00', [
            'terminal_out' => AttendanceRecord::AUTO_SHIFT_CLOSE_TERMINAL,
        ]);

        $this->assertSame(480, (int) $record->duration_minutes);
        $this->assertNoOvertimeOrStimulation($record);
    }

    public function test_saturday_night_shift_ending_sunday_is_classified_as_saturday(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, false);
        $shift = $this->shift($department, '22:00:00', '06:00:00');

        $record = $this->punch($employee, $shift, '2026-10-24 22:00:00', '2026-10-25 06:00:00');

        $this->assertSame(480, (int) $record->duration_minutes);
        $this->assertOnlyOvertime($record, 480, '2026-10-24');
    }

    public function test_friday_night_shift_keeps_weekday_rules_when_stimulacija_is_set(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, true);
        $shift = $this->shift($department, '22:00:00', '06:00:00');

        $record = $this->punch($employee, $shift, '2026-10-23 22:00:00', '2026-10-24 06:00:00');

        $this->assertSame(480, (int) $record->duration_minutes);
        $this->assertNoOvertimeOrStimulation($record);
    }

    public function test_auto_shift_close_and_missing_duration_create_neither_bucket(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, true);
        $shift = $this->shift($department);

        $autoClosed = $this->punch($employee, $shift, '2026-10-24 08:00:00', '2026-10-24 16:00:00', [
            'terminal_out' => AttendanceRecord::AUTO_SHIFT_CLOSE_TERMINAL,
        ]);
        $this->assertNotNull($autoClosed->duration_minutes);
        $this->assertNoOvertimeOrStimulation($autoClosed);

        $open = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'entry_time' => Carbon::parse('2026-10-25 08:00:00', 'UTC'),
            'status' => 'working',
        ]);
        $this->assertNull($open->duration_minutes);
        $this->assertNoOvertimeOrStimulation($open);

        $cleared = $this->punch($employee, $shift, '2026-10-25 08:00:00', '2026-10-25 12:00:00');
        $this->assertOnlyStimulation($cleared, 240, '2026-10-25');

        $cleared->exit_time = null;
        $cleared->save();
        $cleared->refresh();

        $this->assertNull($cleared->duration_minutes);
        $this->assertNoOvertimeOrStimulation($cleared);
    }

    public function test_effective_start_decides_the_weekend_day(): void
    {
        $department = $this->department(false);
        $employee = $this->employee($department, true);
        $shift = $this->shift($department);

        $record = $this->punch($employee, $shift, '2026-10-23 22:00:00', '2026-10-24 08:00:00', [
            'effective_start' => Carbon::parse('2026-10-24 00:30:00', 'UTC'),
        ]);

        $this->assertSame(450, (int) $record->duration_minutes);
        $this->assertOnlyStimulation($record, 450, '2026-10-24');
    }

    private function department(bool $regularWeekend): Department
    {
        return Department::create([
            'name' => 'Odjel ' . ($regularWeekend ? 'redovan' : 'ostali') . ' ' . $this->empSeq,
            'vikend_redovan' => $regularWeekend,
        ]);
    }

    private function employee(?Department $department, bool $stimulacija): Employee
    {
        $this->empSeq++;

        return Employee::create([
            'empID' => $this->empSeq,
            'firstName' => 'Test',
            'lastName' => 'Radnik ' . $this->empSeq,
            'dept' => $department ? (string) $department->id : null,
            'stimulacija' => $stimulacija,
            'Active' => true,
        ]);
    }

    private function shift(Department $department, string $start = '08:00:00', string $end = '16:00:00'): Shift
    {
        return Shift::create([
            'department_id' => $department->id,
            'name' => $start . '-' . $end,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    private function punch(Employee $employee, Shift $shift, string $start, string $end, array $extra = []): AttendanceRecord
    {
        return AttendanceRecord::create(array_merge([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'entry_time' => Carbon::parse($start, 'UTC'),
            'exit_time' => Carbon::parse($end, 'UTC'),
            'status' => 'left',
        ], $extra));
    }

    private function assertOnlyOvertime(AttendanceRecord $record, int $minutes, string $workDate): void
    {
        $overtime = AttendanceOvertime::query()->where('attendance_record_id', $record->id)->first();
        $stimulation = AttendanceStimulation::query()->where('attendance_record_id', $record->id)->first();

        $this->assertNotNull($overtime);
        $this->assertNull($stimulation);
        $this->assertSame($minutes, (int) $overtime->overtime_minutes);
        $this->assertSame($workDate, $overtime->work_date->toDateString());
    }

    private function assertOnlyStimulation(AttendanceRecord $record, int $minutes, string $workDate): void
    {
        $overtime = AttendanceOvertime::query()->where('attendance_record_id', $record->id)->first();
        $stimulation = AttendanceStimulation::query()->where('attendance_record_id', $record->id)->first();

        $this->assertNull($overtime);
        $this->assertNotNull($stimulation);
        $this->assertSame($minutes, (int) $stimulation->minutes);
        $this->assertSame($workDate, $stimulation->work_date->toDateString());
    }

    private function assertNoOvertimeOrStimulation(AttendanceRecord $record): void
    {
        $this->assertNull(AttendanceOvertime::query()->where('attendance_record_id', $record->id)->first());
        $this->assertNull(AttendanceStimulation::query()->where('attendance_record_id', $record->id)->first());
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('attendance_stimulations');
        Schema::dropIfExists('attendance_overtimes');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('vikend_redovan')->default(false);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empID')->unique();
            $table->string('firstName');
            $table->string('lastName');
            $table->string('dept')->nullable();
            $table->boolean('stimulacija')->default(false);
            $table->boolean('Active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('attendance_credit_code')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->timestamp('entry_time')->nullable();
            $table->timestamp('effective_start')->nullable();
            $table->timestamp('exit_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('status')->default('working');
            $table->string('late_flag')->nullable();
            $table->string('terminal_in')->nullable();
            $table->string('terminal_out')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_overtimes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_record_id')->unique();
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->integer('overtime_minutes');
            $table->timestamps();
        });

        Schema::create('attendance_stimulations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_record_id')->unique();
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->integer('minutes');
            $table->timestamps();
        });
    }
}

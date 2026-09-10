<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_schedule_marks_once_for_automatic_employee(): void
    {
        $schedule = $this->splitSchedule();
        $employee = Employee::factory()->create([
            'attendance_method' => 'automatic',
            'work_schedule_id' => $schedule->id,
            'hire_date' => '2026-01-01',
        ]);

        $this->artisan('attendance:generate-automatic', ['date' => '2026-09-01'])->assertSuccessful();
        $this->artisan('attendance:generate-automatic', ['date' => '2026-09-01'])->assertSuccessful();

        $marks = Attendance::where('employee_id', $employee->id)->orderBy('checked_at')->get();
        $this->assertSame(
            ['08:00', '12:00', '14:00', '18:00'],
            $marks->map(fn (Attendance $attendance): string => $attendance->checked_at->format('H:i'))->all(),
        );
        $this->assertSame(['in', 'out', 'in', 'out'], $marks->pluck('type')->all());
        $this->assertSame(['automatic'], $marks->pluck('source')->unique()->values()->all());
    }

    public function test_attendance_page_ensures_today_automatic_marks_exist(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00'));
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $schedule = $this->splitSchedule();
        $employee = Employee::factory()->create([
            'attendance_method' => 'automatic',
            'work_schedule_id' => $schedule->id,
        ]);

        $this->withoutVite()->actingAs($administrator)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee($employee->last_name)
            ->assertSee('Automática');

        $this->assertSame(4, Attendance::where('employee_id', $employee->id)->count());
    }

    public function test_command_completes_missing_automatic_marks(): void
    {
        $schedule = $this->splitSchedule();
        $employee = Employee::factory()->create([
            'attendance_method' => 'automatic',
            'work_schedule_id' => $schedule->id,
        ]);
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'checked_at' => '2026-09-01 08:00:00',
            'source' => 'automatic',
        ]);

        $this->artisan('attendance:generate-automatic', ['date' => '2026-09-01'])->assertSuccessful();

        $this->assertSame(4, Attendance::where('employee_id', $employee->id)->count());
    }

    public function test_command_skips_physical_mode_and_approved_leave(): void
    {
        $schedule = $this->splitSchedule();
        Employee::factory()->create(['attendance_method' => 'biometric', 'work_schedule_id' => $schedule->id]);
        $automaticEmployee = Employee::factory()->create(['attendance_method' => 'automatic', 'work_schedule_id' => $schedule->id]);
        $leaveType = LeaveType::where('code', 'personal')->firstOrFail();
        LeaveRequest::factory()->for($automaticEmployee)->for($leaveType)->create([
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-01',
            'status' => 'approved',
        ]);

        $this->artisan('attendance:generate-automatic', ['date' => '2026-09-01'])->assertSuccessful();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_command_skips_holiday(): void
    {
        $schedule = $this->splitSchedule();
        Employee::factory()->create(['attendance_method' => 'automatic', 'work_schedule_id' => $schedule->id]);
        Holiday::factory()->create(['date' => '2026-09-01']);

        $this->artisan('attendance:generate-automatic', ['date' => '2026-09-01'])->assertSuccessful();

        $this->assertDatabaseCount('attendances', 0);
    }

    private function splitSchedule(): WorkSchedule
    {
        $schedule = WorkSchedule::create(['name' => 'Horario ejecutivo', 'is_active' => true]);
        $schedule->days()->create([
            'day_of_week' => 2,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'second_starts_at' => '14:00',
            'second_ends_at' => '18:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);

        return $schedule;
    }
}

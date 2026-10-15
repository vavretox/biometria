<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlternatingScheduleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_schedules_apply_on_the_selected_weekdays(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $first = WorkSchedule::factory()->create();
        $second = WorkSchedule::factory()->create();

        foreach ([[$first, [1, 3, 5]], [$second, [2, 4]]] as [$schedule, $weekdays]) {
            $this->actingAs($admin)->post(route('schedules.assign'), [
                'assignment_scope' => 'selected', 'employee_ids' => [$employee->id],
                'work_schedule_id' => $schedule->id, 'ends_on' => '2026-10-31',
                'starts_on' => '2026-10-01', 'weekdays' => $weekdays,
            ])->assertSessionHasNoErrors();
        }

        $employee->refresh();
        $this->assertSame($first->id, $employee->scheduleForDate(CarbonImmutable::parse('2026-10-05'))?->id);
        $this->assertSame($second->id, $employee->scheduleForDate(CarbonImmutable::parse('2026-10-06'))?->id);
        $employee->load('scheduleAssignments.workSchedule');
        $this->assertSame($first->id, $employee->scheduleForDate(CarbonImmutable::parse('2026-10-07'))?->id);
        $this->assertSame($second->id, $employee->scheduleForDate(CarbonImmutable::parse('2026-10-08'))?->id);
        $this->assertNull($employee->attendanceScheduleForDate(CarbonImmutable::parse('2026-10-10')));
        $this->assertDatabaseCount('work_schedule_assignments', 2);
    }

    public function test_a_third_schedule_is_rejected_and_the_entire_batch_is_rolled_back(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $availableEmployee = Employee::factory()->create();
        $employee = Employee::factory()->create();
        foreach ([[1, 3, 5], [2, 4]] as $weekdays) {
            WorkScheduleAssignment::factory()->create([
                'employee_id' => $employee->id, 'starts_on' => '2026-10-01',
                'ends_on' => null, 'weekdays' => $weekdays,
            ]);
        }
        $third = WorkSchedule::factory()->create();

        $this->actingAs($admin)->from(route('schedules.index'))->post(route('schedules.assign'), [
            'assignment_scope' => 'selected', 'employee_ids' => [$availableEmployee->id, $employee->id],
            'work_schedule_id' => $third->id, 'ends_on' => '2026-10-31',
            'starts_on' => '2026-10-01', 'weekdays' => [6],
        ])->assertSessionHasErrors(['schedule_limit' => $employee->full_name.' ya tiene dos horarios en el periodo indicado. No se puede asignar un tercer horario.']);

        $this->assertDatabaseCount('work_schedule_assignments', 2);
        $this->assertDatabaseMissing('work_schedule_assignments', ['work_schedule_id' => $third->id]);
        $this->withoutVite()->get(route('schedules.index'))->assertSee('schedule-limit-dialog')->assertSee('Límite de horarios');
    }

    public function test_a_new_schedule_after_the_previous_periods_is_allowed(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        WorkScheduleAssignment::factory()->count(2)->create([
            'employee_id' => $employee->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30',
        ]);
        $schedule = WorkSchedule::factory()->create();

        $this->actingAs($admin)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected', 'employee_ids' => [$employee->id],
            'work_schedule_id' => $schedule->id, 'ends_on' => '2026-10-31',
            'starts_on' => '2026-10-01', 'weekdays' => [1, 3, 5],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('work_schedule_assignments', 3);
    }

    public function test_reassigning_selected_days_preserves_other_days_and_surrounding_dates(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $old = WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id, 'starts_on' => '2026-09-01', 'ends_on' => null,
        ]);
        $schedule = WorkSchedule::factory()->create();

        $this->actingAs($admin)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected', 'employee_ids' => [$employee->id],
            'work_schedule_id' => $schedule->id, 'ends_on' => '2026-10-31',
            'starts_on' => '2026-10-01', 'weekdays' => [1, 3, 5],
        ])->assertSessionHasNoErrors();

        $employee->refresh()->load('scheduleAssignments.workSchedule');
        $this->assertSame($old->work_schedule_id, $employee->scheduleForDate(CarbonImmutable::parse('2026-09-30'))?->id);
        $this->assertSame([1], $employee->scheduleAssignments()->firstOrFail()->weekdays);
        $this->assertSame($schedule->id, $employee->scheduleForDate(CarbonImmutable::parse('2026-10-05'))?->id);
        $this->assertSame($old->work_schedule_id, $employee->scheduleForDate(CarbonImmutable::parse('2026-10-06'))?->id);
        $this->assertSame($old->work_schedule_id, $employee->scheduleForDate(CarbonImmutable::parse('2026-11-02'))?->id);
    }

    public function test_an_assignment_can_start_and_end_on_the_same_day(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $schedule = WorkSchedule::factory()->create();

        $this->actingAs($admin)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected', 'employee_ids' => [$employee->id],
            'work_schedule_id' => $schedule->id, 'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-05', 'weekdays_present' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame([1], $employee->scheduleAssignments()->firstOrFail()->weekdays);
        $this->assertSame($schedule->id, $employee->scheduleForDate(CarbonImmutable::parse('2026-10-05'))?->id);
        $this->assertNull($employee->scheduleForDate(CarbonImmutable::parse('2026-10-06')));
    }

    public function test_the_end_date_is_required(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $schedule = WorkSchedule::factory()->create();

        $this->actingAs($admin)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected', 'employee_ids' => [$employee->id],
            'work_schedule_id' => $schedule->id, 'starts_on' => '2026-10-05', 'weekdays' => [1],
        ])->assertSessionHasErrors(['ends_on' => 'Seleccione la fecha final del horario.']);

        $this->assertDatabaseCount('work_schedule_assignments', 0);
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $schedule = WorkSchedule::factory()->create();

        $this->actingAs($admin)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected', 'employee_ids' => [$employee->id],
            'work_schedule_id' => $schedule->id, 'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-04', 'weekdays' => [1],
        ])->assertSessionHasErrors(['ends_on' => 'La fecha final debe ser igual o posterior a la fecha de inicio.']);

        $this->assertDatabaseCount('work_schedule_assignments', 0);
    }

    public function test_assignments_are_hidden_until_an_employee_is_selected(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        WorkScheduleAssignment::factory()->create();

        $this->withoutVite()->actingAs($admin)->get(route('schedules.index'))
            ->assertViewHas('assignments', null)
            ->assertSee('Seleccione un empleado para consultar sus horarios e historial.');
    }

    public function test_the_filter_returns_only_the_selected_employees_assignments(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $selected = WorkScheduleAssignment::factory()->create();
        WorkScheduleAssignment::factory()->create();

        $this->withoutVite()->actingAs($admin)->get(route('schedules.index', ['employee_id' => $selected->employee_id]))
            ->assertViewHas('assignments', fn ($assignments): bool => $assignments->total() === 1 && $assignments->first()->id === $selected->id);
    }

    public function test_empty_weekdays_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $schedule = WorkSchedule::factory()->create();

        $this->actingAs($admin)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected', 'employee_ids' => [$employee->id],
            'work_schedule_id' => $schedule->id, 'ends_on' => '2026-10-31',
            'starts_on' => '2026-10-01', 'weekdays' => [],
        ])->assertSessionHasErrors('weekdays');

        $this->assertDatabaseCount('work_schedule_assignments', 0);
    }
}

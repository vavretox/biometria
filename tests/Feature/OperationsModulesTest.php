<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_register_a_manual_attendance(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();

        $this->actingAs($administrator)->post(route('attendance.store'), [
            'employee_id' => $employee->id,
            'checked_at' => '2026-08-28 08:05:00',
            'type' => 'in',
            'status' => 'late',
            'observation' => 'Ingreso autorizado por Recursos Humanos.',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'type' => 'in',
            'status' => 'late',
            'source' => 'manual',
            'observation' => 'Ingreso autorizado por Recursos Humanos.',
        ]);
    }

    public function test_an_administrator_can_update_and_delete_an_attendance(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $attendance = Attendance::factory()->create([
            'employee_id' => $employee->id,
            'checked_at' => '2026-09-01 08:00:00',
            'type' => 'in',
            'status' => 'normal',
            'late_minutes' => 0,
        ]);

        $this->actingAs($administrator)->put(route('attendance.update', $attendance), [
            'checked_at' => '2026-09-01 08:12:00',
            'type' => 'in',
            'status' => 'late',
            'late_minutes' => 12,
            'observation' => 'Se corrigió la hora según soporte presentado.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'late',
            'late_minutes' => 12,
            'observation' => 'Se corrigió la hora según soporte presentado.',
        ]);

        $this->actingAs($administrator)->delete(route('attendance.destroy', $attendance))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    public function test_a_regular_user_cannot_update_or_delete_an_attendance(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $attendance = Attendance::factory()->create([
            'employee_id' => $employee->id,
            'checked_at' => '2026-09-01 08:00:00',
            'type' => 'in',
            'status' => 'normal',
        ]);

        $this->actingAs($user)->put(route('attendance.update', $attendance), [
            'checked_at' => '2026-09-01 08:00:00',
            'type' => 'in',
            'status' => 'normal',
            'late_minutes' => 0,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('attendance.destroy', $attendance))->assertForbidden();

        $this->assertModelExists($attendance);
    }

    public function test_attendance_page_groups_the_day_and_compares_marks_with_the_schedule(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $schedule = WorkSchedule::factory()->create(['name' => 'Horario continuo']);
        $schedule->days()->create([
            'day_of_week' => 2,
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);
        $employee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);
        Attendance::factory()->create(['employee_id' => $employee->id, 'checked_at' => '2026-09-01 08:03:00']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'checked_at' => '2026-09-01 16:01:00', 'type' => 'out']);

        $this->withoutVite()->actingAs($administrator)->get(route('attendance.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-01',
        ]))
            ->assertSee('Marcaciones del día')
            ->assertSee('Esperado: 08:00')
            ->assertSee('Esperado: 16:00')
            ->assertSee('Completo 2/2')
            ->assertSee('Marcación')
            ->assertSee('Registrar permiso')
            ->assertSee('Editar')
            ->assertSee('Eliminar');
    }

    public function test_an_authenticated_user_can_filter_attendances_by_period_and_see_the_assigned_schedule(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $employee = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
        $schedule = WorkSchedule::factory()->create(['name' => 'Horario administrativo']);
        WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ]);
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'checked_at' => '2026-08-15 08:00:00',
            'type' => 'in',
            'status' => 'normal',
            'source' => 'manual',
        ]);
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'checked_at' => '2026-09-01 08:00:00',
            'type' => 'in',
            'status' => 'normal',
            'source' => 'manual',
        ]);

        $this->withoutVite()->actingAs($user)->get(route('attendance.index', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]))
            ->assertSee('Desde')
            ->assertSee('Hasta')
            ->assertSee('15/08/2026')
            ->assertSee('Horario administrativo')
            ->assertDontSee('01/09/2026');
    }

    public function test_an_administrator_can_create_and_assign_a_weekly_schedule(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();

        $this->actingAs($administrator)->post(route('schedules.store'), [
            'name' => 'Horario administrativo',
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'grace_minutes' => 10,
        ])->assertRedirect();

        $schedule = WorkSchedule::where('name', 'Horario administrativo')->firstOrFail();
        $this->assertCount(5, $schedule->days);

        $this->actingAs($administrator)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected',
            'employee_ids' => [$employee->id],
            'work_schedule_id' => $schedule->id,
            'ends_on' => today()->addYear()->toDateString(),
            'starts_on' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'work_schedule_id' => $schedule->id]);
        $this->assertDatabaseHas('work_schedule_assignments', [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'ends_on' => today()->addYear()->toDateString().' 00:00:00',
        ]);
    }

    public function test_an_administrator_can_assign_a_schedule_to_some_or_all_active_employees(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $schedule = WorkSchedule::create([
            'name' => 'Horario operativo',
            'is_active' => true,
        ]);
        $selectedEmployees = Employee::factory()->count(2)->create();
        $otherEmployee = Employee::factory()->create();

        $this->actingAs($administrator)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected',
            'employee_ids' => $selectedEmployees->modelKeys(),
            'work_schedule_id' => $schedule->id,
            'ends_on' => today()->addYear()->toDateString(),
            'starts_on' => today()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success', 'Horario asignado a 2 empleado(s) para el periodo indicado.');

        $selectedEmployees->each(fn (Employee $employee) => $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'work_schedule_id' => $schedule->id,
        ]));
        $this->assertDatabaseHas('employees', ['id' => $otherEmployee->id, 'work_schedule_id' => null]);

        $this->actingAs($administrator)->post(route('schedules.assign'), [
            'assignment_scope' => 'all',
            'work_schedule_id' => $schedule->id,
            'ends_on' => today()->addYear()->toDateString(),
            'starts_on' => today()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success', 'Horario asignado a 3 empleado(s) para el periodo indicado.');

        $this->assertSame(3, Employee::where('work_schedule_id', $schedule->id)->count());
    }

    public function test_an_administrator_can_schedule_a_temporary_assignment_and_preserve_surrounding_periods(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $regularSchedule = WorkSchedule::factory()->create(['name' => 'Horario regular']);
        $temporarySchedule = WorkSchedule::factory()->create(['name' => 'Horario temporal']);
        $employee = Employee::factory()->create(['work_schedule_id' => $regularSchedule->id]);
        WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $regularSchedule->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $this->actingAs($administrator)->post(route('schedules.assign'), [
            'assignment_scope' => 'selected',
            'employee_ids' => [$employee->id],
            'work_schedule_id' => $temporarySchedule->id,
            'ends_on' => '2026-08-16',
            'starts_on' => '2026-08-10',
        ])->assertRedirect();

        $this->assertDatabaseHas('work_schedule_assignments', [
            'employee_id' => $employee->id,
            'work_schedule_id' => $regularSchedule->id,
            'starts_on' => '2026-01-01 00:00:00',
            'ends_on' => '2026-08-09 00:00:00',
        ]);
        $this->assertDatabaseHas('work_schedule_assignments', [
            'employee_id' => $employee->id,
            'work_schedule_id' => $temporarySchedule->id,
            'starts_on' => '2026-08-10 00:00:00',
            'ends_on' => '2026-08-16 00:00:00',
        ]);
        $this->assertDatabaseHas('work_schedule_assignments', [
            'employee_id' => $employee->id,
            'work_schedule_id' => $regularSchedule->id,
            'starts_on' => '2026-08-17 00:00:00',
            'ends_on' => '2026-12-31 00:00:00',
        ]);

        $this->withoutVite()->actingAs($administrator)->get(route('schedules.index', ['employee_id' => $employee->id]))
            ->assertOk()
            ->assertSee('Empleados con horario asignado')
            ->assertSee($employee->last_name)
            ->assertSee('Horario temporal');
    }

    public function test_an_administrator_can_delete_an_unused_schedule(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $schedule = WorkSchedule::create(['name' => 'Horario temporal', 'is_active' => true]);
        $schedule->days()->create([
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);

        $this->actingAs($administrator)->delete(route('schedules.destroy', $schedule))
            ->assertRedirect()
            ->assertSessionHas('success', 'Horario eliminado correctamente.');

        $this->assertDatabaseMissing('work_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('work_schedule_days', ['work_schedule_id' => $schedule->id]);
    }

    public function test_an_administrator_can_edit_a_schedule_without_losing_assignments(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $schedule = WorkSchedule::create(['name' => 'Horario anterior', 'is_active' => true]);
        $schedule->days()->create([
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);
        $employee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);

        $this->actingAs($administrator)->put(route('schedules.update', $schedule), [
            'name' => 'Horario discontinuo actualizado',
            'starts_at' => '08:30',
            'ends_at' => '12:30',
            'second_starts_at' => '14:30',
            'second_ends_at' => '18:30',
            'grace_minutes' => 7,
        ])->assertRedirect()->assertSessionHas('success', 'Horario actualizado correctamente.');

        $this->assertDatabaseHas('work_schedules', ['id' => $schedule->id, 'name' => 'Horario discontinuo actualizado']);
        $this->assertDatabaseHas('work_schedule_days', [
            'work_schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'starts_at' => '08:30',
            'ends_at' => '12:30',
            'second_starts_at' => '14:30',
            'second_ends_at' => '18:30',
            'grace_minutes' => 7,
        ]);
        $this->assertSame($schedule->id, $employee->fresh()->work_schedule_id);
    }

    public function test_an_assigned_schedule_cannot_be_deleted(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $schedule = WorkSchedule::create(['name' => 'Horario en uso', 'is_active' => true]);
        Employee::factory()->create(['work_schedule_id' => $schedule->id]);

        $this->actingAs($administrator)->delete(route('schedules.destroy', $schedule))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertModelExists($schedule);
    }

    public function test_an_administrator_can_disable_the_afternoon_shift_when_editing_a_schedule(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $schedule = WorkSchedule::create(['name' => 'Horario discontinuo', 'is_active' => true]);
        $schedule->days()->create([
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'second_starts_at' => '15:00',
            'second_ends_at' => '19:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);

        $this->actingAs($administrator)->put(route('schedules.update', $schedule), [
            'name' => 'Horario continuo',
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'grace_minutes' => 5,
        ])->assertRedirect()->assertSessionHas('success', 'Horario actualizado correctamente.');

        $day = $schedule->days()->firstOrFail();

        $this->assertNull($day->second_starts_at);
        $this->assertNull($day->second_ends_at);
    }

    public function test_an_administrator_can_register_and_approve_a_leave_request(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::where('code', 'medical')->firstOrFail();

        $this->actingAs($administrator)->post(route('leaves.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => '2026-08-28',
            'ends_on' => '2026-08-29',
            'duration_type' => 'all_day',
            'reason' => 'Baja médica',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::firstOrFail();
        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame($administrator->id, $leaveRequest->approved_by);
        $this->assertNotNull($leaveRequest->approved_at);

        $this->actingAs($administrator)->put(route('leaves.approve', $leaveRequest))->assertRedirect();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'approved_by' => $administrator->id,
        ]);
    }

    public function test_an_administrator_can_register_an_hourly_leave_request(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::where('code', 'personal')->firstOrFail();

        $this->actingAs($administrator)->post(route('leaves.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-01',
            'duration_type' => 'hours',
            'starts_at' => '10:00',
            'ends_at' => '12:30',
            'reason' => 'Diligencia personal.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $employee->id,
            'starts_at' => '10:00',
            'ends_at' => '12:30',
        ]);
    }

    public function test_an_administrator_can_register_a_half_day_leave_request(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::where('code', 'personal')->firstOrFail();

        $this->actingAs($administrator)->post(route('leaves.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-01',
            'duration_type' => 'half_day',
            'half_day_period' => 'afternoon',
            'reason' => 'Diligencia personal.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $employee->id,
            'duration_type' => 'half_day',
            'half_day_period' => 'afternoon',
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function test_an_authenticated_user_can_view_the_operational_reports(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Employee::factory()->create();

        $this->withoutVite()->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reporte de asistencia');
    }

    public function test_report_filters_marks_by_unit_employee_and_historical_schedule_type(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $workUnit = WorkUnit::factory()->create(['name' => 'Recursos Humanos']);
        $employee = Employee::factory()->create([
            'work_unit_id' => $workUnit->id,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
        ]);
        $continuousSchedule = WorkSchedule::factory()->create(['name' => 'Administrativo continuo']);
        $continuousSchedule->days()->create([
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);
        $splitSchedule = WorkSchedule::factory()->create(['name' => 'Administrativo discontinuo']);
        $splitSchedule->days()->create([
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'second_starts_at' => '14:00',
            'second_ends_at' => '18:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);
        WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $continuousSchedule->id,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-15',
        ]);
        WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $splitSchedule->id,
            'starts_on' => '2026-08-16',
            'ends_on' => null,
        ]);
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'checked_at' => '2026-08-10 08:00:00',
            'type' => 'in',
            'status' => 'normal',
            'source' => 'biometric',
        ]);
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'checked_at' => '2026-08-20 08:10:00',
            'type' => 'in',
            'status' => 'late',
            'late_minutes' => 10,
            'source' => 'biometric',
        ]);
        Holiday::factory()->create([
            'name' => 'Día institucional',
            'date' => '2026-08-12',
        ]);

        $this->withoutVite()->actingAs($user)->get(route('reports.index', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'work_unit_id' => $workUnit->id,
            'employee_id' => $employee->id,
            'schedule_type' => 'continuous',
        ]))
            ->assertSee('Recursos Humanos')
            ->assertSee('Día institucional')
            ->assertSee('Administrativo continuo')
            ->assertSee('Marcaciones filtradas')
            ->assertSee('>1<', false)
            ->assertDontSee('Administrativo discontinuo');
    }

    public function test_report_shows_each_schedule_period_when_an_assignment_changes_between_dates(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $employee = Employee::factory()->create(['first_name' => 'Luis', 'last_name' => 'Rojas']);
        $firstSchedule = WorkSchedule::factory()->create(['name' => 'Horario inicial']);
        $firstSchedule->days()->create([
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);
        $secondSchedule = WorkSchedule::factory()->create(['name' => 'Horario posterior']);
        $secondSchedule->days()->create([
            'day_of_week' => 1,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);
        WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $firstSchedule->id,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-15',
        ]);
        WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $secondSchedule->id,
            'starts_on' => '2026-08-16',
            'ends_on' => null,
        ]);

        $this->withoutVite()->actingAs($user)->get(route('reports.index', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'employee_id' => $employee->id,
        ]))
            ->assertSee('Horario inicial')
            ->assertSee('Horario posterior')
            ->assertSee('01/08/2026')
            ->assertSee('15/08/2026')
            ->assertSee('16/08/2026')
            ->assertSee('31/08/2026');
    }
}

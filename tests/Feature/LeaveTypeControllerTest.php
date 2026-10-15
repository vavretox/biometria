<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_a_leave_type(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('leave-types.store'), [
            'name' => 'Permiso por estudio',
        ])->assertRedirect()->assertSessionHas('success', 'Tipo de permiso creado correctamente.');

        $this->assertDatabaseHas('leave_types', [
            'name' => 'Permiso por estudio',
            'code' => 'permiso_por_estudio',
            'abbreviation' => 'PE',
            'color' => '#0891B2',
            'is_active' => true,
        ]);
    }

    public function test_regular_user_cannot_manage_leave_types(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $this->actingAs($user)->post(route('leave-types.store'), [
            'name' => 'Permiso especial',
        ])->assertForbidden();

        $this->assertDatabaseMissing('leave_types', ['name' => 'Permiso especial']);
    }

    public function test_administrator_can_rename_and_disable_a_leave_type(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $leaveType = LeaveType::factory()->create(['name' => 'Capacitación', 'code' => 'capacitacion']);

        $this->actingAs($administrator)->put(route('leave-types.update', $leaveType), [
            'name' => 'Formación profesional',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHas('success', 'Tipo de permiso actualizado correctamente.');

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'name' => 'Formación profesional',
            'code' => 'capacitacion',
            'is_active' => false,
        ]);
    }

    public function test_leave_request_rejects_an_inactive_leave_type(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create(['is_active' => false]);

        $this->actingAs($administrator)->post(route('leaves.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-02',
            'duration_type' => 'all_day',
        ])->assertSessionHasErrors('leave_type_id');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_used_leave_type_cannot_be_deleted(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $leaveType = LeaveType::factory()->create();
        LeaveRequest::factory()->for($leaveType)->create();

        $this->actingAs($administrator)->delete(route('leave-types.destroy', $leaveType))
            ->assertRedirect()->assertSessionHasErrors('leave_type');

        $this->assertModelExists($leaveType);
    }

    public function test_unused_leave_type_can_be_deleted(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $leaveType = LeaveType::factory()->create();

        $this->actingAs($administrator)->delete(route('leave-types.destroy', $leaveType))
            ->assertRedirect()->assertSessionHas('success', 'Tipo de permiso eliminado correctamente.');

        $this->assertModelMissing($leaveType);
    }
}

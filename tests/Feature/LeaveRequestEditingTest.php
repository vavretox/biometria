<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_update_a_permission_from_the_roster(): void
    {
        $type = LeaveType::factory()->create();
        $approver = User::factory()->create(['is_admin' => true]);
        $approvedAt = now()->subDay()->startOfSecond();
        $leave = LeaveRequest::factory()->create(['leave_type_id' => $type->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => $approvedAt, 'reason' => 'Consulta médica']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true, 'is_active' => true]));
        $filters = ['from' => '2026-09-01', 'to' => '2026-09-02'];

        $this->get(route('attendance.index', $filters))->assertOk()->assertSee('Editar permiso')->assertSee('Eliminar permiso');
        $this->get(route('attendance.index', [...$filters, 'edit_leave' => $leave->id]))
            ->assertOk()->assertSee('Editar permiso')->assertSee('Consulta médica')->assertSee(route('leaves.update', ['leaveRequest' => $leave, ...$filters]));
        $this->put(route('leaves.update', ['leaveRequest' => $leave, ...$filters]), [
            'employee_id' => $leave->employee_id, 'leave_type_id' => $type->id,
            'starts_on' => '2026-09-02', 'ends_on' => '2026-09-02',
            'duration_type' => 'hours', 'starts_at' => '09:00', 'ends_at' => '11:00', 'reason' => 'Control médico',
            'status' => 'rejected',
        ])->assertRedirect(route('attendance.index', $filters))->assertSessionHas('success');

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'type' => $type->code, 'duration_type' => 'hours', 'reason' => 'Control médico', 'status' => 'approved']);
        $this->assertSame('2026-09-02', $leave->fresh()->starts_on->toDateString());
        $this->assertSame('09:00', substr($leave->fresh()->starts_at, 0, 5));
        $this->assertSame($approver->id, $leave->fresh()->approved_by);
        $this->assertTrue($leave->fresh()->approved_at->equalTo($approvedAt));
    }

    public function test_saving_a_pending_permission_approves_it_automatically(): void
    {
        $this->freezeTime();
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $type = LeaveType::factory()->create();
        $leave = LeaveRequest::factory()->create(['leave_type_id' => $type->id, 'status' => 'pending', 'approved_by' => null, 'approved_at' => null]);

        $this->actingAs($administrator)->put(route('leaves.update', $leave), [
            'employee_id' => $leave->employee_id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-10-07',
            'ends_on' => '2026-10-07',
            'duration_type' => 'all_day',
        ])->assertRedirect()->assertSessionHas('success');

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($administrator->id, $leave->approved_by);
        $this->assertTrue($leave->approved_at->equalTo(now()));
    }

    public function test_admin_can_delete_a_permission_and_it_disappears_from_the_roster(): void
    {
        $leave = LeaveRequest::factory()->create(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-02', 'status' => 'pending']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true, 'is_active' => true]));
        $url = route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-02']);

        $this->from($url)->delete(route('leaves.destroy', $leave))->assertRedirect($url)->assertSessionHas('success');

        $this->assertModelMissing($leave);
        $this->get($url)->assertOk()->assertDontSee('Eliminar permiso');
    }

    public function test_non_admin_cannot_edit_or_delete_permissions_or_see_actions(): void
    {
        $leave = LeaveRequest::factory()->create(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-01']);
        $original = $leave->getAttributes();
        $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => false, 'is_active' => true]));

        $this->put(route('leaves.update', $leave), [])->assertForbidden();
        $this->delete(route('leaves.destroy', $leave))->assertForbidden();
        $this->get(route('attendance.index', ['from' => '2026-09-01', 'edit_leave' => $leave->id]))
            ->assertOk()->assertDontSee('Editar permiso')->assertDontSee('Eliminar permiso');

        $this->assertSame($original, $leave->fresh()->getAttributes());
    }

    public function test_invalid_edit_preserves_permission_and_reopens_form_with_input(): void
    {
        $type = LeaveType::factory()->create();
        $leave = LeaveRequest::factory()->create(['leave_type_id' => $type->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01']);
        $original = $leave->getAttributes();
        $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true, 'is_active' => true]));
        $url = route('attendance.index', ['from' => '2026-09-01', 'edit_leave' => $leave->id]);

        $this->from($url)->put(route('leaves.update', $leave), [
            'form_context' => 'leave', 'employee_id' => $leave->employee_id, 'leave_type_id' => $type->id,
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-02',
            'duration_type' => 'half_day', 'half_day_period' => 'afternoon', 'reason' => 'Corrección pendiente',
        ])->assertRedirect($url)->assertSessionHasErrors('ends_on');

        $this->assertSame($original, $leave->fresh()->getAttributes());
        $this->get($url)->assertOk()->assertSee('Corrección pendiente')->assertSee('Editar permiso');
    }

    public function test_changing_duration_clears_irrelevant_fields_and_keeps_existing_inactive_type(): void
    {
        $type = LeaveType::factory()->create(['is_active' => false]);
        $leave = LeaveRequest::factory()->create(['leave_type_id' => $type->id, 'duration_type' => 'hours', 'starts_at' => '09:00', 'ends_at' => '10:00']);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_active' => true]));

        $this->put(route('leaves.update', $leave), [
            'employee_id' => $leave->employee_id, 'leave_type_id' => $type->id,
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'duration_type' => 'all_day',
            'starts_at' => '09:00', 'ends_at' => '10:00', 'half_day_period' => 'morning',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'duration_type' => 'all_day', 'starts_at' => null, 'ends_at' => null, 'half_day_period' => null]);
    }

    public function test_guests_cannot_update_or_delete_permissions(): void
    {
        $leave = LeaveRequest::factory()->create();

        $this->put(route('leaves.update', $leave), [])->assertRedirect(route('login'));
        $this->delete(route('leaves.destroy', $leave))->assertRedirect(route('login'));

        $this->assertModelExists($leave);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_edit_a_unit_and_keep_its_code(): void
    {
        $unit = WorkUnit::factory()->create();
        $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('office-units.index'))->assertSee('Editar')->assertSee('Eliminar');

        $this->put(route('work-units.update', $unit), ['name' => 'Unidad actualizada', 'code' => $unit->code, 'description' => 'Nueva descripción'])
            ->assertRedirectToRoute('office-units.index');

        $this->assertSame('Unidad actualizada', $unit->fresh()->name);
        $this->assertSame('Nueva descripción', $unit->fresh()->description);
    }

    public function test_administrator_can_delete_a_unit_without_employees(): void
    {
        $unit = WorkUnit::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->delete(route('work-units.destroy', $unit))->assertRedirectToRoute('office-units.index');

        $this->assertModelMissing($unit);
    }

    public function test_a_unit_with_employees_cannot_be_deleted(): void
    {
        $employee = Employee::factory()->create();
        $unit = $employee->workUnit;
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->delete(route('work-units.destroy', $unit))->assertSessionHasErrors('work_unit');

        $this->assertModelExists($unit);
        $this->assertSame($unit->id, $employee->fresh()->work_unit_id);
    }

    public function test_regular_users_cannot_edit_or_delete_units(): void
    {
        $unit = WorkUnit::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->put(route('work-units.update', $unit), ['name' => 'Cambiar', 'code' => 'CAM'])->assertForbidden();
        $this->delete(route('work-units.destroy', $unit))->assertForbidden();

        $this->assertModelExists($unit);
    }

    public function test_administrator_can_create_independent_office_unit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/work-units', [
            'name' => 'Recursos Humanos',
            'code' => 'RRHH',
            'description' => 'Administración del personal',
        ])->assertRedirect();

        $this->assertDatabaseHas('work_units', [
            'code' => 'RRHH',
            'name' => 'Recursos Humanos',
        ]);
        $this->assertDatabaseHas('offices', ['code' => 'GENERAL']);
    }
}

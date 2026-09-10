<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeManagementTest extends TestCase
{
    use RefreshDatabase;

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

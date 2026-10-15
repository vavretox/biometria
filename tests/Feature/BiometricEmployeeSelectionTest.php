<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Biometric\BiometricSyncService;
use Mockery\MockInterface;
use Tests\TestCase;

class BiometricEmployeeSelectionTest extends TestCase
{
    public function test_missing_or_empty_selection_never_sends_all_employees(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1, 'is_admin' => true, 'is_active' => true]));
        $this->mock(BiometricSyncService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('pushEmployees');
        });

        $this->postJson(route('biometric.devices.sync-employees', 1), [])
            ->assertUnprocessable()->assertJsonValidationErrors('employee_ids');
        $this->postJson(route('biometric.devices.sync-employees', 1), ['employee_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('employee_ids');
    }

    public function test_regular_user_cannot_list_or_send_employees(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1, 'is_admin' => false, 'is_active' => true]));
        $this->mock(BiometricSyncService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('pushEmployees');
        });

        $this->getJson(route('biometric.employees-for-sync'))->assertForbidden();
        $this->postJson(route('biometric.devices.sync-employees', 1), ['employee_ids' => [1]])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Biometric\BiometricDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricDeviceEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_devices_have_an_edit_link_and_a_prefilled_form_without_secrets(): void
    {
        $device = $this->createDevice();
        $this->withoutVite()->actingAs(User::factory()->create());

        $this->get(route('biometric.devices.index'))->assertOk()->assertSee(route('biometric.devices.edit', $device));
        $this->get(route('biometric.devices.edit', $device))->assertOk()
            ->assertSee('192.168.1.100')->assertSee('Guardar cambios')->assertDontSee('existing-secret');
    }

    public function test_update_changes_network_and_disables_sync_without_losing_history(): void
    {
        $device = $this->createDevice();
        $device->communicationLogs()->create(['command' => 'connect', 'status' => 'success']);

        $this->actingAs(User::factory()->create())->put(route('biometric.devices.update', $device), [
            ...$this->payload(), 'device_ip' => '10.100.1.92', 'connection_status' => 'connected', 'last_sync' => null,
        ])->assertRedirect(route('biometric.devices.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('biometric_devices', [
            'id' => $device->id, 'device_ip' => '10.100.1.92', 'auto_sync' => false,
            'connection_status' => 'disconnected', 'last_sync' => '2026-09-01 12:00:00', 'api_secret' => 'existing-secret',
        ]);
        $this->assertSame(1, $device->communicationLogs()->count());
    }

    public function test_invalid_network_and_duplicate_name_leave_device_unchanged(): void
    {
        $device = $this->createDevice();
        $other = $this->createDevice('Otro reloj');

        $this->actingAs(User::factory()->create())->from(route('biometric.devices.edit', $device))
            ->put(route('biometric.devices.update', $device), [...$this->payload(), 'device_name' => $other->device_name, 'device_ip' => 'invalid', 'device_port' => 70000])
            ->assertRedirect(route('biometric.devices.edit', $device))
            ->assertSessionHasErrors(['device_name', 'device_ip', 'device_port']);

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id, 'device_name' => 'Reloj central', 'device_ip' => '192.168.1.100']);
    }

    public function test_blank_api_credentials_preserve_existing_values_and_new_values_replace_them(): void
    {
        $device = $this->createDevice();
        $this->actingAs(User::factory()->create());

        $this->put(route('biometric.devices.update', $device), [...$this->payload(), 'protocol' => 'HTTPAPI', 'api_key' => '', 'api_secret' => ''])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id, 'api_secret' => 'existing-secret']);

        $this->put(route('biometric.devices.update', $device), [...$this->payload(), 'protocol' => 'HTTPAPI', 'api_key' => 'new-key', 'api_secret' => 'new-secret'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id, 'api_key' => 'new-key', 'api_secret' => 'new-secret']);
    }

    public function test_guests_cannot_edit_or_update_devices(): void
    {
        $device = $this->createDevice();

        $this->get(route('biometric.devices.edit', $device))->assertRedirect(route('login'));
        $this->put(route('biometric.devices.update', $device), $this->payload())->assertRedirect(route('login'));
    }

    private function createDevice(string $name = 'Reloj central'): BiometricDevice
    {
        return BiometricDevice::create([...$this->payload(), 'device_name' => $name, 'auto_sync' => true, 'api_secret' => 'existing-secret', 'connection_status' => 'connected', 'last_sync' => '2026-09-01 12:00:00']);
    }

    /** @return array<string, string|int|bool> */
    private function payload(): array
    {
        return [
            'device_name' => 'Reloj central', 'device_ip' => '192.168.1.100', 'device_port' => 4370,
            'manufacturer' => 'ZKSoftware', 'device_model' => 'iClock 580', 'location' => 'DTI',
            'protocol' => 'ZKProtocol', 'timezone' => 'America/La_Paz',
            'connection_timeout' => 30, 'sync_interval' => 60, 'auto_sync' => false, 'is_active' => true,
        ];
    }
}

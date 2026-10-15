<?php

namespace Tests\Feature;

use App\Models\Biometric\BiometricDevice;
use App\Models\User;
use App\Services\Biometric\BiometricDeviceService;
use App\Services\Biometric\Protocols\ProtocolInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BiometricConnectionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_records_failures_continues_with_other_devices_and_skips_inactive_devices(): void
    {
        $failedDevice = $this->createDevice('Sin respuesta');
        $availableDevice = $this->createDevice('Disponible');
        $inactiveDevice = $this->createDevice('Inactivo', ['is_active' => false]);
        $failedProtocol = Mockery::mock(ProtocolInterface::class);
        $failedProtocol->shouldReceive('connect')->once()->andReturnFalse();
        $availableProtocol = Mockery::mock(ProtocolInterface::class);
        $availableProtocol->shouldReceive('connect')->once()->andReturnTrue();
        $availableProtocol->shouldReceive('setTime')->once()->andReturnTrue();
        $availableProtocol->shouldReceive('disconnect')->once()->andReturnTrue();
        $service = $this->partialMock(BiometricDeviceService::class);
        $service->shouldReceive('getProtocolInstance')->once()->withArgs(fn ($device) => $device->id === $failedDevice->id)->andReturn($failedProtocol);
        $service->shouldReceive('getProtocolInstance')->once()->withArgs(fn ($device) => $device->id === $availableDevice->id)->andReturn($availableProtocol);

        $this->artisan('biometric:check-connections')->assertExitCode(1);

        $this->assertDatabaseHas('biometric_devices', ['id' => $failedDevice->id, 'connection_status' => 'error']);
        $this->assertDatabaseHas('biometric_devices', ['id' => $availableDevice->id, 'connection_status' => 'connected']);
        $this->assertSame('automatic', $failedDevice->communicationLogs()->where('command', 'connect')->sole()->request_data['source']);
        $this->assertSame('automatic', $availableDevice->communicationLogs()->where('command', 'connect')->sole()->request_data['source']);
        $this->assertNotNull($availableDevice->communicationLogs()->where('command', 'connect')->sole()->response_time_ms);
        $this->assertSame(0, $inactiveDevice->communicationLogs()->count());
    }

    public function test_monitor_clears_an_error_after_connection_recovers(): void
    {
        $device = $this->createDevice('Recuperado', ['connection_status' => 'error', 'connection_error' => 'Sin respuesta']);
        $protocol = Mockery::mock(ProtocolInterface::class);
        $protocol->shouldReceive('connect')->once()->andReturnTrue();
        $protocol->shouldReceive('setTime')->once()->andReturnTrue();
        $protocol->shouldReceive('disconnect')->once()->andReturnTrue();
        $this->partialMock(BiometricDeviceService::class)->shouldReceive('getProtocolInstance')->andReturn($protocol);

        $this->artisan('biometric:check-connections')->assertExitCode(0);

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id, 'connection_status' => 'connected', 'connection_error' => null]);
        $this->assertSame('success', $device->communicationLogs()->where('command', 'connect')->sole()->status);
    }

    public function test_module_warns_about_failures_and_expired_checks_instead_of_showing_them_as_connected(): void
    {
        $this->freezeTime();
        $this->createDevice('Reloj sin respuesta', ['connection_status' => 'error', 'connection_error' => 'Sin respuesta UDP']);
        $stale = $this->createDevice('Reloj vencido', ['last_connection_test' => now()->subMinutes(36)]);
        $this->createDevice('Reloj reciente', ['last_connection_test' => now()->subMinutes(30)]);
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $this->get(route('biometric.devices.index'))->assertOk()
            ->assertSee('Sin respuesta UDP')->assertSee('Verificación vencida')
            ->assertSee('Historial de conexión')->assertSee('Verificación automática cada 30 minutos');

        $this->assertFalse($stale->isConnected());
        $this->assertTrue($stale->isConnectionCheckOverdue());
    }

    public function test_history_filters_by_device_result_and_dates_and_escapes_errors(): void
    {
        $device = $this->createDevice('Reloj A');
        $other = $this->createDevice('Reloj B');
        $this->travelTo('2026-10-07 10:00:00');
        $device->communicationLogs()->create(['command' => 'connect', 'status' => 'failed', 'error_message' => '<script>error</script>', 'request_data' => ['source' => 'automatic']]);
        $device->communicationLogs()->create(['command' => 'connect', 'status' => 'success', 'error_message' => 'EXCLUDED_SUCCESS']);
        $this->travelTo('2026-10-06 10:00:00');
        $device->communicationLogs()->create(['command' => 'connect', 'status' => 'failed', 'error_message' => 'EXCLUDED_DATE']);
        $this->travelTo('2026-10-07 10:00:00');
        $other->communicationLogs()->create(['command' => 'connect', 'status' => 'failed', 'error_message' => 'EXCLUDED_DEVICE']);
        $device->communicationLogs()->create(['command' => 'get_users', 'status' => 'failed', 'error_message' => 'EXCLUDED_COMMAND']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $this->get(route('biometric.devices.connection-history', ['device_id' => $device->id, 'status' => 'failed', 'start_date' => '2026-10-07', 'end_date' => '2026-10-07']))
            ->assertOk()->assertSee('&lt;script&gt;error&lt;/script&gt;', false)->assertDontSee('<script>error</script>', false)
            ->assertSee('Automática')->assertDontSee('EXCLUDED_SUCCESS')->assertDontSee('EXCLUDED_DATE')
            ->assertDontSee('EXCLUDED_DEVICE')->assertDontSee('EXCLUDED_COMMAND');
    }

    public function test_history_rejects_invalid_filters_and_accepts_an_end_date_without_a_start_date(): void
    {
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $this->getJson(route('biometric.devices.connection-history', ['status' => 'invalid', 'device_id' => 999999, 'start_date' => 'bad-date']))
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'device_id', 'start_date']);
        $this->get(route('biometric.devices.connection-history', ['end_date' => '2026-10-07']))->assertOk();
    }

    public function test_guests_cannot_access_connection_history(): void
    {
        $this->get(route('biometric.devices.connection-history'))->assertRedirect(route('login'));
    }

    /** @param array<string, mixed> $attributes */
    private function createDevice(string $name, array $attributes = []): BiometricDevice
    {
        return BiometricDevice::create([
            'device_name' => $name, 'device_ip' => '192.168.250.26', 'device_port' => 4370,
            'manufacturer' => 'ZKSoftware', 'protocol' => 'ZKProtocol', 'timezone' => 'America/Caracas',
            'is_active' => true, 'connection_status' => 'connected', 'last_connection_test' => now(),
            ...$attributes,
        ]);
    }
}

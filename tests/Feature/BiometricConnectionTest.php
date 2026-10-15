<?php

namespace Tests\Feature;

use App\Models\Biometric\BiometricDevice;
use App\Models\User;
use App\Services\Biometric\BiometricDeviceService;
use App\Services\Biometric\Protocols\ProtocolInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BiometricConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_connection_test_preserves_status_after_closing_the_session(): void
    {
        $device = $this->createDevice('disconnected');
        $protocol = Mockery::mock(ProtocolInterface::class);
        $protocol->shouldReceive('connect')->once()->andReturnTrue();
        $protocol->shouldReceive('setTime')->once()->andReturnTrue();
        $protocol->shouldReceive('disconnect')->once()->andReturnTrue();
        $this->useProtocol($protocol);

        $this->actingAs(User::factory()->create(['is_active' => true]))->postJson(route('biometric.devices.test', $device))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('status', 'connected');

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id, 'connection_status' => 'connected', 'connection_error' => null]);
        $this->assertNotNull($device->fresh()->last_connection_test);
    }

    public function test_connection_test_sends_bolivian_time_and_records_the_result(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 15:25:00', 'UTC'));
        $device = $this->createDevice('disconnected');
        $protocol = Mockery::mock(ProtocolInterface::class);
        $protocol->shouldReceive('connect')->once()->andReturnTrue();
        $protocol->shouldReceive('setTime')->once()->withArgs(fn ($time): bool => $time->format('Y-m-d H:i:s') === '2026-10-08 11:25:00' && $time->timezoneName === 'America/La_Paz')->andReturnTrue();
        $protocol->shouldReceive('disconnect')->once()->andReturnTrue();
        $this->useProtocol($protocol);

        $this->actingAs(User::factory()->create(['is_active' => true]))->postJson(route('biometric.devices.test', $device))
            ->assertJsonPath('time_synchronized', true)->assertJsonPath('timezone', 'America/La_Paz');

        $this->assertSame('America/La_Paz', $device->fresh()->timezone);
        $this->assertSame('success', $device->communicationLogs()->where('command', 'set_time')->sole()->status);
    }

    public function test_a_clock_sync_failure_keeps_the_connection_status_and_warns_the_user(): void
    {
        $device = $this->createDevice('disconnected');
        $protocol = Mockery::mock(ProtocolInterface::class);
        $protocol->shouldReceive('connect')->once()->andReturnTrue();
        $protocol->shouldReceive('setTime')->once()->andReturnFalse();
        $protocol->shouldReceive('disconnect')->once()->andReturnTrue();
        $this->useProtocol($protocol);

        $this->actingAs(User::factory()->create(['is_active' => true]))->postJson(route('biometric.devices.test', $device))
            ->assertJsonPath('success', true)->assertJsonPath('status', 'connected')
            ->assertJsonPath('time_synchronized', false)
            ->assertJsonPath('message', 'Conexión exitosa, pero no se pudo sincronizar la hora: El biométrico no confirmó la sincronización de hora.');

        $this->assertSame('failed', $device->communicationLogs()->where('command', 'set_time')->sole()->status);
    }

    public function test_false_connection_result_returns_failure_and_replaces_previous_connected_status(): void
    {
        $device = $this->createDevice('connected');
        $protocol = Mockery::mock(ProtocolInterface::class);
        $protocol->shouldReceive('connect')->once()->andReturnFalse();
        $protocol->shouldNotReceive('disconnect');
        $this->useProtocol($protocol);

        $this->actingAs(User::factory()->create(['is_active' => true]))->postJson(route('biometric.devices.test', $device))
            ->assertOk()->assertJsonPath('success', false)->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'No se pudo conectar al dispositivo');

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id, 'connection_status' => 'error', 'connection_error' => 'No se pudo conectar al dispositivo']);
        $this->assertNotNull($device->fresh()->last_connection_test);
    }

    public function test_connection_exception_is_shown_to_the_user(): void
    {
        $device = $this->createDevice('connected');
        $protocol = Mockery::mock(ProtocolInterface::class);
        $protocol->shouldReceive('connect')->once()->andThrow(new \RuntimeException('El reloj no responde'));
        $this->useProtocol($protocol);

        $this->actingAs(User::factory()->create(['is_active' => true]))->postJson(route('biometric.devices.test', $device))
            ->assertOk()->assertJsonPath('success', false)->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'El reloj no responde');

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id, 'connection_status' => 'error', 'connection_error' => 'El reloj no responde']);
    }

    public function test_reading_users_stops_if_connection_fails(): void
    {
        $device = $this->createDevice('connected');
        $protocol = Mockery::mock(ProtocolInterface::class);
        $protocol->shouldReceive('connect')->once()->andReturnFalse();
        $protocol->shouldNotReceive('getUsers');
        $service = $this->useProtocol($protocol);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo conectar al dispositivo');

        $service->getUsers($device->id);
    }

    private function useProtocol(ProtocolInterface $protocol): BiometricDeviceService
    {
        $service = $this->partialMock(BiometricDeviceService::class);
        $service->shouldReceive('getProtocolInstance')->andReturn($protocol);

        return $service;
    }

    private function createDevice(string $status): BiometricDevice
    {
        return BiometricDevice::create([
            'device_name' => 'Reloj central', 'device_ip' => '192.168.250.26', 'device_port' => 4370,
            'manufacturer' => 'ZKSoftware', 'protocol' => 'ZKProtocol', 'timezone' => 'America/Caracas',
            'connection_status' => $status,
        ]);
    }
}

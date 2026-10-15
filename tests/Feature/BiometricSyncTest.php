<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Biometric\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Biometric\BiometricDeviceService;
use App\Services\Biometric\BiometricSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BiometricSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_devices_page_uses_the_actual_device_foreign_keys(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $device = $this->createDevice();
        $device->communicationLogs()->create(['command' => 'connect', 'status' => 'success']);

        $this->withoutVite()->actingAs($user)->get(route('biometric.devices.index'))
            ->assertOk()
            ->assertSee('Reloj central')
            ->assertSee('Biométrico → sistema')
            ->assertSee('Sistema → biométrico')
            ->assertSee('Descargar usuarios y huellas')
            ->assertSee('Enviar empleados y códigos');

        $this->assertSame(1, $device->communicationLogs()->count());
    }

    public function test_it_registers_a_zk_device_without_connection_type_or_api_credentials(): void
    {
        $administrator = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('testConnection')->once()->andReturnTrue();
        });

        $response = $this->actingAs($administrator)->post(route('biometric.devices.store'), [
            'device_name' => 'iClock 580 DTI',
            'device_ip' => '192.168.100.2',
            'device_port' => 4370,
            'device_model' => 'iClock 580',
            'manufacturer' => 'ZKSoftware',
            'location' => 'Oficina DTI',
            'protocol' => 'ZKProtocol',
            'timezone' => 'America/La_Paz',
            'connection_timeout' => 30,
            'sync_interval' => 60,
        ]);

        $response->assertRedirect(route('biometric.devices.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('biometric_devices', [
            'device_ip' => '192.168.100.2',
            'connection_type' => 'TCP/IP',
            'api_key' => null,
            'api_secret' => null,
        ]);
    }

    public function test_it_downloads_and_encrypts_fingerprint_templates_for_a_known_employee(): void
    {
        $device = $this->createDevice();
        $employee = Employee::factory()->create(['employee_code' => '105', 'biometric_id' => null]);
        $gateway = $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->once()->andReturnTrue();
            $mock->shouldReceive('getUsers')->once()->andReturn([['uid' => 105, 'userid' => '105', 'name' => 'Ana']]);
            $mock->shouldReceive('getFingerprints')->once()->withArgs(fn ($deviceId, $uid) => $uid === 105)->andReturn([2 => 'binary-template']);
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $result = (new BiometricSyncService($gateway))->pullEmployeesAndFingerprints($device);

        $this->assertSame(['matched' => 1, 'created' => 0, 'fingerprints' => 1, 'unmatched' => 0, 'without_fingerprints' => 0], $result);
        $this->assertSame(105, $employee->fresh()->biometric_id);
        $template = BiometricTemplate::firstOrFail();
        $this->assertSame(base64_encode('binary-template'), $template->template_data);
        $this->assertNotSame(base64_encode('binary-template'), $template->getRawOriginal('template_data'));
    }

    public function test_it_reports_when_a_matched_device_user_has_no_exportable_fingerprints(): void
    {
        $device = $this->createDevice();
        Employee::factory()->create(['employee_code' => '001', 'biometric_id' => 1]);
        $gateway = $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->once()->andReturnTrue();
            $mock->shouldReceive('getUsers')->once()->andReturn([['uid' => 1, 'userid' => '001', 'name' => 'Horacio']]);
            $mock->shouldReceive('getFingerprints')->once()->withArgs(fn ($deviceId, $uid) => $uid === 1)->andReturn([]);
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $result = (new BiometricSyncService($gateway))->pullEmployeesAndFingerprints($device);

        $this->assertSame(['matched' => 1, 'created' => 0, 'fingerprints' => 0, 'unmatched' => 0, 'without_fingerprints' => 1], $result);
        $this->assertSame(0, BiometricTemplate::count());
    }

    public function test_it_can_send_only_employees_without_attempting_to_send_fingerprints(): void
    {
        $device = $this->createDevice();
        $employee = Employee::factory()->create(['biometric_id' => 45]);
        BiometricTemplate::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'finger_index' => 1,
            'template_data' => base64_encode('stored-template'),
            'checksum' => hash('sha256', 'stored-template'),
            'synced_at' => now(),
        ]);
        $gateway = $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->once()->andReturnTrue();
            $mock->shouldReceive('setUser')->once()->andReturnTrue();
            $mock->shouldNotReceive('setFingerprints');
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $result = (new BiometricSyncService($gateway))->pushEmployees($device, [$employee->id]);

        $this->assertSame(['sent' => 1, 'fingerprints' => 0, 'errors' => 0], $result);
    }

    public function test_it_sends_the_access_code_as_the_device_password(): void
    {
        $device = $this->createDevice();
        $employee = Employee::factory()->create([
            'biometric_id' => 46,
            'attendance_method' => 'access_code',
            'access_code' => '7391',
        ]);
        $gateway = $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->once()->andReturnTrue();
            $mock->shouldReceive('setUser')->once()->withArgs(
                fn ($deviceId, array $data): bool => $data['uid'] === 46 && $data['password'] === '7391',
            )->andReturnTrue();
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $result = (new BiometricSyncService($gateway))->pushEmployees($device, [$employee->id]);

        $this->assertSame(['sent' => 1, 'fingerprints' => 0, 'errors' => 0], $result);
    }

    public function test_it_imports_a_mark_and_evaluates_lateness_using_the_employee_schedule(): void
    {
        $device = $this->createDevice();
        $schedule = WorkSchedule::create(['name' => 'Administrativo', 'is_active' => true]);
        $schedule->days()->create([
            'day_of_week' => 5,
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'grace_minutes' => 10,
            'is_working_day' => true,
        ]);
        $employee = Employee::factory()->create(['biometric_id' => 77, 'work_schedule_id' => $schedule->id]);
        $gateway = $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->once()->andReturnTrue();
            $mock->shouldReceive('getAttendance')->once()->andReturn([
                ['uid' => 77, 'state' => 0, 'timestamp' => '2026-08-28 08:20:00'],
            ]);
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $result = (new BiometricSyncService($gateway))->pullAttendance($device);

        $this->assertSame(['synced' => 1, 'duplicates' => 0, 'unmatched' => 0, 'ignored' => 0, 'invalid' => 0], $result);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'type' => 'in',
            'status' => 'late',
            'source' => 'biometric',
        ]);
    }

    public function test_it_ignores_device_marks_with_an_impossible_future_date(): void
    {
        $device = $this->createDevice();
        $gateway = $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->once()->andReturnTrue();
            $mock->shouldReceive('getAttendance')->once()->andReturn([
                ['uid' => 1, 'state' => 0, 'timestamp' => '2103-08-21 23:51:27'],
            ]);
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $result = (new BiometricSyncService($gateway))->pullAttendance($device);

        $this->assertSame(['synced' => 0, 'duplicates' => 0, 'unmatched' => 0, 'ignored' => 0, 'invalid' => 1], $result);
        $this->assertSame(0, Attendance::count());
    }

    public function test_it_accumulates_lateness_across_morning_and_afternoon_entries(): void
    {
        $device = $this->createDevice();
        $schedule = WorkSchedule::create(['name' => 'Discontinuo', 'is_active' => true]);
        $schedule->days()->create([
            'day_of_week' => 5,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
            'second_starts_at' => '15:00',
            'second_ends_at' => '19:00',
            'grace_minutes' => 5,
            'is_working_day' => true,
        ]);
        $employee = Employee::factory()->create(['biometric_id' => 88, 'work_schedule_id' => $schedule->id]);
        $gateway = $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->once()->andReturnTrue();
            $mock->shouldReceive('getAttendance')->once()->andReturn([
                ['uid' => 88, 'state' => 0, 'timestamp' => '2026-08-28 08:03:00'],
                ['uid' => 88, 'state' => 0, 'timestamp' => '2026-08-28 15:04:00'],
            ]);
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        (new BiometricSyncService($gateway))->pullAttendance($device);

        $marks = $employee->attendances()->orderBy('checked_at')->get();
        $this->assertSame(3, $marks[0]->late_minutes);
        $this->assertSame('normal', $marks[0]->status);
        $this->assertSame(4, $marks[1]->late_minutes);
        $this->assertSame('late', $marks[1]->status);
        $this->assertSame(7, $marks->sum('late_minutes'));
    }

    private function createDevice(): BiometricDevice
    {
        return BiometricDevice::create([
            'device_name' => 'Reloj central',
            'device_ip' => '192.168.1.100',
            'device_port' => 4370,
            'manufacturer' => 'ZKSoftware',
            'protocol' => 'ZKProtocol',
            'timezone' => 'America/La_Paz',
            'is_active' => true,
        ]);
    }
}

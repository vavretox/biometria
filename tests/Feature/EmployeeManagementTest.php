<?php

namespace Tests\Feature;

use App\Models\Biometric\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_employee(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $data = Employee::factory()->make()->only([
            'office_id', 'work_unit_id', 'employee_code', 'item_number',
            'employee_type', 'budget_line', 'first_name', 'last_name', 'identity_document',
            'maternal_last_name', 'identity_issued_in', 'has_children', 'address',
            'email', 'phone', 'mobile', 'position', 'job_description',
            'employment_observations', 'memo_date', 'memo_number', 'department',
            'biometric_id', 'attendance_method', 'access_code', 'hire_date', 'employment_end_date', 'birth_date',
            'marital_status', 'gender',
        ]);
        $data['hire_date'] = $data['hire_date']->format('Y-m-d');
        $data['birth_date'] = $data['birth_date']->format('Y-m-d');
        $data['memo_date'] = $data['memo_date']->format('Y-m-d');
        $data['is_active'] = true;

        $this->actingAs($admin)->post('/employees', $data)
            ->assertRedirectToRoute('employees.index');

        $this->assertDatabaseHas('employees', [
            'employee_code' => $data['employee_code'],
            'office_id' => $data['office_id'],
            'work_unit_id' => $data['work_unit_id'],
            'item_number' => $data['item_number'],
            'maternal_last_name' => $data['maternal_last_name'],
            'identity_issued_in' => $data['identity_issued_in'],
            'address' => $data['address'],
            'birth_date' => $data['birth_date'].' 00:00:00',
        ]);
    }

    public function test_regular_user_cannot_create_employee(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post('/employees', [])->assertForbidden();
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_administrator_can_create_employee_with_encrypted_access_code(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $data = Employee::factory()->make([
            'attendance_method' => 'access_code',
            'biometric_id' => null,
        ])->only([
            'work_unit_id', 'employee_code', 'item_number', 'employee_type', 'first_name',
            'last_name', 'identity_document', 'attendance_method', 'biometric_id',
        ]);
        $data['access_code'] = '845931';

        $this->actingAs($administrator)->post(route('employees.store'), $data)->assertRedirectToRoute('employees.index');

        $employee = Employee::where('employee_code', $data['employee_code'])->firstOrFail();
        $this->assertSame('845931', $employee->access_code);
        $this->assertNotSame('845931', $employee->getRawOriginal('access_code'));
    }

    public function test_employee_listing_shows_the_biometric_id_and_stored_fingerprint_count(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $employee = Employee::factory()->create([
            'employee_code' => '001',
            'biometric_id' => 1,
            'attendance_method' => 'biometric',
        ]);
        $device = BiometricDevice::create([
            'device_name' => 'Reloj central',
            'device_ip' => '192.168.1.100',
            'device_port' => 4370,
            'manufacturer' => 'ZKSoftware',
            'protocol' => 'ZKProtocol',
            'timezone' => 'America/Caracas',
            'is_active' => true,
        ]);

        foreach ([0, 1, 2] as $fingerIndex) {
            BiometricTemplate::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'finger_index' => $fingerIndex,
                'template_data' => base64_encode('template-'.$fingerIndex),
                'checksum' => hash('sha256', 'template-'.$fingerIndex),
                'synced_at' => now(),
            ]);
        }

        $this->withoutVite()->actingAs($administrator)->get(route('employees.index'))
            ->assertSee('ID biométrico 1')
            ->assertSee('3 huellas registradas');
    }
}

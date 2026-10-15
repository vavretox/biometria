<?php

namespace Tests\Feature;

use App\Models\Biometric\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_biometric_ids_increment_and_ignore_manual_values(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Employee::factory()->create(['biometric_id' => 40])->delete();

        foreach ([41, 42] as $expectedId) {
            $data = $this->employeeUpdateData(Employee::factory()->make());
            $data['biometric_id'] = 999;

            $this->post(route('employees.store'), $data)->assertRedirectToRoute('employees.index');

            $employee = Employee::where('employee_code', $data['employee_code'])->firstOrFail();
            $this->assertSame($expectedId, (int) $employee->biometric_id);
        }
    }

    public function test_biometric_id_starts_at_one_and_cannot_be_changed(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $data = $this->employeeUpdateData(Employee::factory()->make());
        unset($data['biometric_id']);

        $this->post(route('employees.store'), $data)->assertRedirectToRoute('employees.index');
        $employee = Employee::where('employee_code', $data['employee_code'])->firstOrFail();
        $this->assertSame(1, (int) $employee->biometric_id);

        $data['biometric_id'] = 999;
        $this->put(route('employees.update', $employee), $data)->assertRedirectToRoute('employees.index');
        $this->assertSame(1, (int) $employee->fresh()->biometric_id);

        $this->withoutVite()->get(route('employees.create'))
            ->assertViewHas('nextBiometricId', 2)
            ->assertSee("value='2' readonly", false);
    }

    public function test_employee_filters_combine_unit_status_and_attendance_method(): void
    {
        $employee = Employee::factory()->create(['attendance_method' => 'automatic', 'is_active' => false]);
        Employee::factory()->create(['work_unit_id' => $employee->work_unit_id, 'attendance_method' => 'automatic', 'is_active' => true]);
        Employee::factory()->create(['work_unit_id' => $employee->work_unit_id, 'attendance_method' => 'biometric', 'is_active' => false]);
        Employee::factory()->create(['attendance_method' => 'automatic', 'is_active' => false]);
        $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('employees.index', [
            'work_unit_id' => $employee->work_unit_id,
            'is_active' => '0',
            'attendance_method' => 'automatic',
        ]))->assertViewHas('employees', fn ($employees): bool => $employees->total() === 1 && $employees->first()->is($employee))
            ->assertSee('Limpiar');

        $this->get(route('employees.index'))->assertViewHas('employees', fn ($employees): bool => $employees->total() === 4);
    }

    public function test_employee_pages_remain_accessible_with_an_unreadable_access_code(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $employee = $this->employeeWithUnreadableAccessCode();

        $this->withoutVite()->actingAs($administrator)->get(route('employees.index'))
            ->assertSee('Código ilegible: volver a configurar');

        $this->get(route('employees.edit', $employee))
            ->assertSee('Código de 4 a 20 caracteres')
            ->assertDontSee('Dejar vacío para conservar');
    }

    public function test_an_unreadable_access_code_requires_a_replacement(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $employee = $this->employeeWithUnreadableAccessCode();
        $ciphertext = $employee->getRawOriginal('access_code');
        $data = $this->employeeUpdateData($employee);

        $this->actingAs($administrator)->put(route('employees.update', $employee), $data)
            ->assertSessionHasErrors('access_code');

        $this->assertSame($ciphertext, $employee->fresh()->getRawOriginal('access_code'));
    }

    public function test_administrator_can_replace_an_unreadable_access_code(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $employee = $this->employeeWithUnreadableAccessCode();
        $data = $this->employeeUpdateData($employee);
        $data['access_code'] = '845931';

        $this->actingAs($administrator)->put(route('employees.update', $employee), $data)
            ->assertRedirectToRoute('employees.index');

        $this->assertSame('845931', $employee->fresh()->access_code);
        $this->assertNotSame('845931', $employee->fresh()->getRawOriginal('access_code'));
    }

    public function test_a_valid_access_code_is_preserved_when_left_blank(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $employee = Employee::factory()->create(['attendance_method' => 'access_code', 'access_code' => '845931']);
        $data = $this->employeeUpdateData($employee);

        $this->actingAs($administrator)->put(route('employees.update', $employee), $data)
            ->assertRedirectToRoute('employees.index');

        $this->assertSame('845931', $employee->fresh()->access_code);
    }

    public function test_employee_listing_distinguishes_configured_and_pending_access_codes(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        Employee::factory()->create(['attendance_method' => 'access_code', 'access_code' => '845931']);
        Employee::factory()->create(['attendance_method' => 'access_code', 'access_code' => null]);

        $this->withoutVite()->actingAs($administrator)->get(route('employees.index'))
            ->assertSee('Código configurado')
            ->assertSee('Código pendiente')
            ->assertDontSee('845931');
    }

    private function employeeWithUnreadableAccessCode(): Employee
    {
        $employee = Employee::factory()->create(['attendance_method' => 'access_code']);
        $encrypter = new Encrypter(str_repeat('x', 32), 'AES-256-CBC');
        $employee->getConnection()->table('employees')->where('id', $employee->id)->update([
            'access_code' => $encrypter->encryptString('7391'),
        ]);

        return $employee->refresh();
    }

    /** @return array<string, mixed> */
    private function employeeUpdateData(Employee $employee): array
    {
        return $employee->only([
            'work_unit_id', 'employee_code', 'item_number', 'employee_type', 'first_name',
            'last_name', 'identity_document', 'attendance_method', 'biometric_id',
        ]);
    }

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

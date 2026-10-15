<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Biometric\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkUnit;
use App\Services\Biometric\BiometricDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_filters_leaves_dates_blank_and_hides_results_until_searching(): void
    {
        Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $this->get(route('attendance.index', ['search' => 'Ana Pérez', 'from' => '2026-09-01', 'to' => '2026-09-01']))
            ->assertSee('Listado de empleados')->assertSee('Pérez Ana');

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertDontSee('Listado de empleados')
            ->assertDontSee('Pérez Ana')
            ->assertSee("name='from' value=''", false)
            ->assertSee("name='to' value=''", false)
            ->assertViewHas('searchRequested', false)
            ->assertViewHas('dailySummaries', fn ($summaries): bool => $summaries->total() === 0);
    }

    public function test_employee_without_marks_appears_with_actions_and_permission_status_without_creating_attendance(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
        $leaveType = LeaveType::factory()->create(['name' => 'Permiso personal']);
        LeaveRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'status' => 'pending']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true, 'is_active' => true]));

        $this->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-01']))
            ->assertOk()->assertSee('Pérez Ana')->assertSee('Sin marcaciones')->assertSee('+ Añadir marcación')
            ->assertSee('+ Permiso')->assertSee('Permiso personal')->assertSee('Pendiente de aprobación');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_search_matches_multiple_name_parts_and_respects_unit_and_mark_filters(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
        $other = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Otro']);
        Attendance::factory()->create(['employee_id' => $other->id, 'checked_at' => '2026-09-01 08:00:00']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'checked_at' => '2026-09-02 08:00:00']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $response = $this->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-01', 'search' => 'Ana Pérez', 'work_unit_id' => $employee->work_unit_id, 'mark_filter' => 'without_marks']));

        $response->assertOk()->assertSee('Pérez Ana')->assertDontSee('Otro Ana');
        $this->assertSame(1, $response->viewData('dailySummaries')->total());
    }

    public function test_pagination_limits_loaded_employees_and_preserves_filters(): void
    {
        $unit = WorkUnit::factory()->create();
        Employee::factory()->count(26)->create(['work_unit_id' => $unit->id, 'office_id' => $unit->office_id, 'first_name' => 'Listado']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $response = $this->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-02', 'search' => 'Listado', 'per_page' => 25]));

        $response->assertOk()->assertSee('page=2', false)->assertSee('search=Listado', false);
        $summaries = $response->viewData('dailySummaries');
        $this->assertSame(26, $summaries->total());
        $this->assertCount(25, $summaries->items());
        $this->assertCount(2, $summaries->items()[0]['days']);
        $this->assertCount(25, $response->viewData('employees'));
    }

    public function test_permission_filter_includes_pending_and_approved_but_excludes_rejected_permissions(): void
    {
        $pending = Employee::factory()->create();
        $approved = Employee::factory()->create();
        $rejected = Employee::factory()->create();
        foreach ([$pending->id => 'pending', $approved->id => 'approved', $rejected->id => 'rejected'] as $id => $status) {
            LeaveRequest::factory()->create(['employee_id' => $id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'status' => $status]);
        }
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $response = $this->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-01', 'mark_filter' => 'with_leave']));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('dailySummaries')->total());
    }

    public function test_afternoon_entry_does_not_fill_the_missing_morning_entry(): void
    {
        $schedule = WorkSchedule::factory()->create(['name' => 'Horario discontinuo']);
        $schedule->days()->create(['day_of_week' => 2, 'starts_at' => '08:00', 'ends_at' => '12:00', 'second_starts_at' => '15:00', 'second_ends_at' => '19:00', 'is_working_day' => true]);
        $employee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);
        Attendance::factory()->create(['employee_id' => $employee->id, 'checked_at' => '2026-09-01 15:01:00', 'type' => 'in']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $response = $this->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-01']));

        $response->assertOk();
        $slots = $response->viewData('dailySummaries')->items()[0]['days'][0]['slots'];
        $this->assertNull($slots[0]['mark']);
        $this->assertSame('15:01', $slots[2]['mark']->checked_at->format('H:i'));
    }

    public function test_device_filter_limits_employees_and_marks_and_preserves_pagination(): void
    {
        $first = BiometricDevice::create(['device_name' => 'Reloj central', 'device_ip' => '192.168.1.100', 'device_port' => 4370, 'manufacturer' => 'ZKSoftware', 'protocol' => 'ZKProtocol', 'timezone' => 'America/La_Paz', 'is_active' => true]);
        $second = BiometricDevice::create(['device_name' => 'Reloj secundario', 'device_ip' => '192.168.1.101', 'device_port' => 4370, 'manufacturer' => 'ZKSoftware', 'protocol' => 'ZKProtocol', 'timezone' => 'America/La_Paz', 'is_active' => false]);
        $employees = Employee::factory()->count(16)->create();
        foreach ($employees as $employee) {
            Attendance::factory()->create(['employee_id' => $employee->id, 'device_id' => $first->id, 'checked_at' => '2026-09-01 08:00', 'type' => 'in']);
        }
        Attendance::factory()->create(['employee_id' => $employees->first()->id, 'device_id' => $second->id, 'checked_at' => '2026-09-01 17:00', 'type' => 'out']);
        $otherEmployee = Employee::factory()->create();
        Attendance::factory()->create(['employee_id' => $otherEmployee->id, 'device_id' => $second->id, 'checked_at' => '2026-09-01 09:00', 'type' => 'in']);
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $response = $this->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-01', 'device_id' => $first->id, 'per_page' => 15]));

        $response->assertOk()->assertSee('Todos los biométricos')->assertSee('Reloj secundario')->assertSee('device_id='.$first->id, false);
        $summaries = $response->viewData('dailySummaries');
        $this->assertSame(16, $summaries->total());
        foreach ($summaries as $summary) {
            $this->assertSame(1, $summary['marked_count']);
        }
        $this->assertNotContains($otherEmployee->id, $summaries->getCollection()->pluck('employee.id')->all());
    }

    public function test_device_filter_includes_linked_employees_without_marks_in_the_period(): void
    {
        $device = BiometricDevice::create(['device_name' => 'Reloj central', 'device_ip' => '192.168.1.100', 'device_port' => 4370, 'manufacturer' => 'ZKSoftware', 'protocol' => 'ZKProtocol', 'timezone' => 'America/La_Paz', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $enrolled = Employee::factory()->create();
        Attendance::factory()->create(['employee_id' => $employee->id, 'device_id' => $device->id, 'checked_at' => '2026-08-31 08:00', 'type' => 'in']);
        BiometricTemplate::factory()->create(['employee_id' => $enrolled->id, 'device_id' => $device->id]);
        Employee::factory()->create();
        $this->withoutVite()->actingAs(User::factory()->create(['is_active' => true]));

        $response = $this->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-01', 'device_id' => $device->id, 'mark_filter' => 'without_marks']));

        $response->assertOk();
        $summaries = $response->viewData('dailySummaries');
        $this->assertSame(2, $summaries->total());
        $this->assertEqualsCanonicalizing([$employee->id, $enrolled->id], $summaries->getCollection()->pluck('employee.id')->all());
    }

    public function test_filters_reject_invalid_dates_and_unbounded_periods(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $this->getJson(route('attendance.index', ['from' => 'not-a-date', 'per_page' => 500]))
            ->assertUnprocessable()->assertJsonValidationErrors(['from', 'per_page']);
        $this->getJson(route('attendance.index', ['from' => '2026-01-01', 'to' => '2026-12-31']))
            ->assertUnprocessable()->assertJsonValidationErrors(['to']);
    }

    public function test_import_creates_users_without_marks_preserves_unknown_personal_data_and_is_idempotent(): void
    {
        $device = BiometricDevice::create(['device_name' => 'Reloj', 'device_ip' => '192.168.250.26', 'device_port' => 4370, 'manufacturer' => 'ZKSoftware', 'protocol' => 'ZKProtocol']);
        $gateway = $this->mock(BiometricDeviceService::class);
        $gateway->shouldReceive('connect')->twice()->andReturnTrue();
        $gateway->shouldReceive('getUsers')->twice()->andReturn([['uid' => 321, 'userid' => '321', 'name' => 'NUEVO USUARIO']]);
        $gateway->shouldNotReceive('getFingerprints');
        $gateway->shouldReceive('disconnect')->twice()->andReturnTrue();
        $gateway->shouldNotReceive('getAttendance');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_active' => true]));

        $this->postJson(route('biometric.devices.pull-biometrics', ['deviceId' => $device->id, 'include_fingerprints' => 0]))->assertOk()->assertJsonPath('data.created', 1);
        $this->postJson(route('biometric.devices.pull-biometrics', ['deviceId' => $device->id, 'include_fingerprints' => 0]))->assertOk()->assertJsonPath('data.created', 0);

        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseHas('employees', ['employee_code' => '321', 'biometric_id' => 321, 'first_name' => 'NUEVO USUARIO', 'identity_document' => null, 'is_active' => true]);
        $this->withoutVite()->get(route('attendance.index', ['from' => '2026-09-01', 'to' => '2026-09-01']))
            ->assertOk()->assertSee('NUEVO USUARIO')->assertSee('Completar ficha del empleado');
    }

    public function test_regular_users_cannot_import_new_employees_from_devices(): void
    {
        $device = BiometricDevice::create(['device_name' => 'Reloj', 'device_ip' => '192.168.250.26', 'manufacturer' => 'ZKSoftware']);
        $this->actingAs(User::factory()->create(['is_active' => true, 'is_admin' => false]));

        $this->postJson(route('biometric.devices.pull-biometrics', $device))->assertForbidden();

        $this->assertDatabaseCount('employees', 0);
    }
}

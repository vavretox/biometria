<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use App\Services\AttendanceSummaryService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendancePermissionCoverageTest extends TestCase
{
    #[DataProvider('permissionCoverage')]
    public function test_only_approved_permissions_cover_the_corresponding_slots(string $duration, ?string $period, string $status, array $covered): void
    {
        $employee = $this->employeeWithPermission($duration, $period, $status);

        $summary = app(AttendanceSummaryService::class)->summarize($employee, Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'));

        $day = $summary['days']->first();
        $this->assertSame($covered, $day['slots']->map(fn (array $slot): bool => $slot['leave'] !== null)->all());
        $this->assertSame(count(array_filter($covered)), $day['completedCount']);
        $this->assertSame(0, $summary['marked_count']);
        $this->assertCount(0, $employee->attendances);

        $this->withoutVite()->actingAs(User::factory()->make(['id' => 1, 'is_admin' => true, 'is_active' => true]));
        $html = view('attendance.index', [
            'from' => Carbon::parse('2026-10-07'), 'to' => Carbon::parse('2026-10-07'),
            'dailySummaries' => new LengthAwarePaginator([$summary], 1, 25),
            'employees' => collect([$employee]), 'workUnits' => collect(), 'devices' => collect(), 'filterDevices' => collect(),
            'editableAttendance' => null, 'editableLeave' => null, 'leaveTypes' => collect(), 'errors' => new ViewErrorBag,
        ])->render();

        $this->assertSame(4 - count(array_filter($covered)), substr_count($html, '>+ Añadir marcación</button>'));
        $this->assertSame(count(array_filter($covered)), substr_count($html, 'Permiso: Baja médica'));
    }

    /** @return array<string, array{string, ?string, string, array<bool>}> */
    public static function permissionCoverage(): array
    {
        return [
            'full day' => ['all_day', null, 'approved', [true, true, true, true]],
            'morning' => ['half_day', 'morning', 'approved', [true, true, false, false]],
            'afternoon' => ['half_day', 'afternoon', 'approved', [false, false, true, true]],
            'hours' => ['hours', null, 'approved', [false, true, true, false]],
            'pending' => ['all_day', null, 'pending', [false, false, false, false]],
            'rejected' => ['all_day', null, 'rejected', [false, false, false, false]],
        ];
    }

    public function test_permission_preserves_real_marks_and_stops_covering_slots_when_removed(): void
    {
        $employee = $this->employeeWithPermission('all_day', null, 'approved');
        $mark = Attendance::factory()->make(['id' => 1, 'employee_id' => 1, 'checked_at' => '2026-10-07 16:31:00', 'type' => 'in', 'source' => 'biometric']);
        $employee->setRelation('attendances', collect([$mark]));
        $service = app(AttendanceSummaryService::class);
        $date = Carbon::parse('2026-10-07');

        $day = $service->summarize($employee, $date, $date)['days']->first();

        $this->assertSame($mark, $day['slots'][2]['mark']);
        $this->assertSame('16:31', $day['slots'][2]['mark']->checked_at->format('H:i'));
        $this->assertSame(4, $day['completedCount']);
        $this->assertSame(3, $day['coveredCount']);

        $employee->setRelation('leaveRequests', collect());
        $day = $service->summarize($employee, $date, $date)['days']->first();

        $this->assertSame(1, $day['completedCount']);
        $this->assertSame(0, $day['coveredCount']);
        $this->assertSame($mark, $day['slots'][2]['mark']);
    }

    public function test_permission_does_not_cover_days_outside_its_dates(): void
    {
        $employee = $this->employeeWithPermission('all_day', null, 'approved');

        $summary = app(AttendanceSummaryService::class)->summarize($employee, Carbon::parse('2026-10-06'), Carbon::parse('2026-10-08'));

        $this->assertSame([0, 4, 0], $summary['days']->pluck('coveredCount')->all());
    }

    private function employeeWithPermission(string $duration, ?string $period, string $status): Employee
    {
        $employee = Employee::factory()->make(['id' => 1, 'office_id' => null, 'work_unit_id' => null]);
        $schedule = WorkSchedule::factory()->make();
        $schedule->setRelation('days', collect([WorkScheduleDay::factory()->make([
            'day_of_week' => 3, 'is_working_day' => true, 'starts_at' => '08:00', 'ends_at' => '12:00',
            'second_starts_at' => '15:00', 'second_ends_at' => '19:00',
        ])]));
        $leave = LeaveRequest::factory()->make([
            'id' => 1, 'employee_id' => 1, 'leave_type_id' => 1, 'status' => $status,
            'starts_on' => '2026-10-07', 'ends_on' => '2026-10-07',
            'duration_type' => $duration, 'half_day_period' => $period, 'starts_at' => '12:00', 'ends_at' => '15:00',
        ]);
        $leave->setRelation('leaveType', LeaveType::factory()->make(['name' => 'Baja médica']));
        $employee->setRelation('workUnit', null);
        $employee->setRelation('workSchedule', $schedule);
        $employee->setRelation('scheduleAssignments', collect());
        $employee->setRelation('attendances', collect());
        $employee->setRelation('leaveRequests', collect([$leave]));

        return $employee;
    }
}

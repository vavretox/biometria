<?php

namespace Tests\Feature;

use App\Exports\UnitAttendanceExport;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkUnit;
use App\Services\DailyAttendanceReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_report_filters_restores_defaults_without_results(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00'));
        $user = User::factory()->create();
        Employee::factory()->create();

        $this->actingAs($user)->get(route('reports.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'custom_fields' => 1,
            'excel_layout' => 'single_sheet',
        ]))->assertSee('Vista previa del Excel')->assertViewHas('reportRequested', true);

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('Vista previa del Excel')
            ->assertDontSee('data-report-sheet', false)
            ->assertViewHas('reportRequested', false)
            ->assertViewHas('dailyReports', fn ($reports): bool => $reports->isEmpty())
            ->assertViewHas('from', fn ($from): bool => $from->toDateString() === '2026-10-01')
            ->assertViewHas('to', fn ($to): bool => $to->toDateString() === '2026-10-31')
            ->assertViewHas('selectedFields', DailyAttendanceReportService::DefaultFields)
            ->assertViewHas('selectedColumns', DailyAttendanceReportService::DefaultColumns);
    }

    public function test_export_builds_daily_marks_holidays_schedules_and_lateness_for_the_unit(): void
    {
        $workUnit = WorkUnit::factory()->create(['name' => 'Recursos Humanos']);
        $employee = Employee::factory()->create([
            'work_unit_id' => $workUnit->id,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'identity_document' => '1234567',
        ]);
        $schedule = WorkSchedule::factory()->create(['name' => 'Horario discontinuo']);
        foreach ([1, 2] as $dayOfWeek) {
            $schedule->days()->create([
                'day_of_week' => $dayOfWeek,
                'starts_at' => '08:00',
                'ends_at' => '12:00',
                'second_starts_at' => '14:00',
                'second_ends_at' => '18:00',
                'grace_minutes' => 5,
                'is_working_day' => true,
            ]);
        }
        WorkScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'starts_on' => '2026-08-01',
            'ends_on' => null,
        ]);
        foreach ([
            ['2026-08-10 08:10:00', 'in', 10],
            ['2026-08-10 12:00:00', 'out', 0],
            ['2026-08-10 14:00:00', 'in', 0],
            ['2026-08-10 18:00:00', 'out', 0],
        ] as [$checkedAt, $type, $lateMinutes]) {
            Attendance::factory()->create([
                'employee_id' => $employee->id,
                'checked_at' => $checkedAt,
                'type' => $type,
                'status' => $lateMinutes > 0 ? 'late' : 'normal',
                'late_minutes' => $lateMinutes,
                'source' => 'biometric',
            ]);
        }
        Holiday::factory()->create(['name' => 'Feriado institucional', 'date' => '2026-08-11']);
        $medicalLeave = LeaveType::where('code', 'medical')->firstOrFail();
        LeaveRequest::factory()->for($employee)->for($medicalLeave)->create([
            'type' => 'medical',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-10',
            'status' => 'approved',
        ]);

        $rows = (new UnitAttendanceExport(
            $workUnit,
            CarbonImmutable::parse('2026-08-10'),
            CarbonImmutable::parse('2026-08-11')->endOfDay(),
        ))->array();

        $this->assertSame(['HORARIO', 'DISC', 'DISC'], $this->rowNamed($rows, 'HORARIO'));
        $this->assertSame(['MAÑANA ENTRADA', '08:10', 'FE'], $this->rowNamed($rows, 'MAÑANA ENTRADA'));
        $this->assertSame(['TARDE SALIDA', '18:00', 'FE'], $this->rowNamed($rows, 'TARDE SALIDA'));
        $this->assertSame(['RET. C/TOL.', 5, ''], $this->rowNamed($rows, 'RET. C/TOL.'));
        $this->assertSame(['INCIDENCIA', 'BM', 'FE'], $this->rowNamed($rows, 'INCIDENCIA'));
        $this->assertContains('BM = Baja médica', $this->rowNamed($rows, 'CÓDIGOS'));
        $this->assertSame(['DÍAS FALTA', 0], $this->rowNamed($rows, 'DÍAS FALTA'));
    }

    public function test_authenticated_user_can_download_the_unit_report_as_xlsx(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $workUnit = WorkUnit::factory()->create(['name' => 'Recursos Humanos']);

        $this->actingAs($user)->get(route('reports.export', [
            'work_unit_id' => $workUnit->id,
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]))->assertDownload('reporte-recursos-humanos-2026-08-01-2026-08-31.xlsx');
    }

    public function test_export_requires_a_work_unit(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->from(route('reports.index'))->get(route('reports.export', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('work_unit_id');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, mixed>
     */
    private function rowNamed(array $rows, string $name): array
    {
        return collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === $name);
    }
}

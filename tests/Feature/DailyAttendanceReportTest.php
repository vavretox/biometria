<?php

namespace Tests\Feature;

use App\Exports\DailyAttendanceSheet;
use App\Exports\DailyAttendanceWorkbook;
use App\Http\Requests\AttendanceReportRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkScheduleDay;
use App\Models\WorkUnit;
use App\Services\DailyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DailyAttendanceReportTest extends TestCase
{
    #[DataProvider('permissionDurations')]
    public function test_permission_codes_are_put_only_in_the_covered_mark_cells(string $duration, ?string $period, string $status, array $values): void
    {
        $employee = $this->employee($duration, $period, $status);

        $report = app(DailyAttendanceReportService::class)->build($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-01'), new EloquentCollection);

        $this->assertSame($values, array_column($report['days'][0]['cells'], 'value'));
        $this->assertSame(0, $report['mark_count']);
        $this->assertCount(0, $employee->attendances);
    }

    /** @return array<string, array{string, ?string, string, array<string>}> */
    public static function permissionDurations(): array
    {
        return [
            'all day' => ['all_day', null, 'approved', ['BM', 'BM', 'BM', 'BM']],
            'morning' => ['half_day', 'morning', 'approved', ['BM', 'BM', 'SIN MARCADO', 'SIN MARCADO']],
            'afternoon' => ['half_day', 'afternoon', 'approved', ['SIN MARCADO', 'SIN MARCADO', 'BM', 'BM']],
            'hours' => ['hours', null, 'approved', ['SIN MARCADO', 'BM', 'BM', 'SIN MARCADO']],
            'pending' => ['all_day', null, 'pending', ['SIN MARCADO', 'SIN MARCADO', 'SIN MARCADO', 'SIN MARCADO']],
            'rejected' => ['all_day', null, 'rejected', ['SIN MARCADO', 'SIN MARCADO', 'SIN MARCADO', 'SIN MARCADO']],
        ];
    }

    public function test_real_marks_are_preserved_with_the_permission_code_and_recorded_minutes(): void
    {
        $employee = $this->employee();
        $mark = Attendance::factory()->make(['id' => 1, 'employee_id' => 1, 'checked_at' => '2026-09-01 15:07', 'type' => 'in', 'source' => 'biometric', 'late_minutes' => 7, 'observation' => 'Dato corregido']);
        $mark->setRelation('device', null);
        $employee->setRelation('attendances', collect([$mark]));

        $report = app(DailyAttendanceReportService::class)->build($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-01'), new EloquentCollection);

        $this->assertSame('15:07 · BM', $report['days'][0]['cells'][2]['value']);
        $this->assertSame('#DC2626', $report['days'][0]['cells'][2]['color']);
        $this->assertSame(1, $report['mark_count']);
        $this->assertSame(3, $report['covered_count']);
        $this->assertSame(7, $report['late_minutes']);
        $this->assertSame(0.0, $report['abandonment_days']);
        $this->assertSame(0.0, $report['unpaid_leave_days']);
        $this->assertSame('Dato corregido', $report['days'][0]['observation']);
    }

    public function test_recurring_holiday_is_shown_on_its_date_and_preserves_real_hours(): void
    {
        $employee = $this->employee();
        $employee->setRelation('leaveRequests', collect());
        $holiday = Holiday::factory()->make(['date' => '2024-09-01', 'is_recurring' => true, 'name' => 'Feriado institucional']);
        $mark = Attendance::factory()->make(['checked_at' => '2026-09-01 08:10', 'type' => 'in', 'source' => 'manual']);
        $mark->setRelation('device', null);
        $employee->setRelation('attendances', collect([$mark]));

        $report = app(DailyAttendanceReportService::class)->build($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-02'), collect([$holiday]));

        $this->assertSame(['08:10', 'FE', 'FE', 'FE'], array_column($report['days'][0]['cells'], 'value'));
        $this->assertSame('FE · Feriado institucional', $report['days'][0]['incidences']);
        $this->assertSame('Feriado', $report['days'][0]['status']);
        $this->assertFalse($report['days'][1]['is_holiday']);
        $this->assertSame(1, $report['holiday_count']);
    }

    public function test_schedule_changes_keep_continuous_exits_in_the_exit_column(): void
    {
        $employee = $this->employee();
        $employee->setRelation('leaveRequests', collect());
        $continuous = WorkSchedule::factory()->make(['name' => 'Horario continuo']);
        $continuous->setRelation('days', collect([WorkScheduleDay::factory()->make(['day_of_week' => 3, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_working_day' => true])]));
        $assignment = WorkScheduleAssignment::factory()->make(['employee_id' => 1, 'work_schedule_id' => 2, 'starts_on' => '2026-09-02', 'ends_on' => null]);
        $assignment->setRelation('workSchedule', $continuous);
        $employee->setRelation('scheduleAssignments', collect([$assignment]));
        $mark = Attendance::factory()->make(['checked_at' => '2026-09-02 17:02', 'type' => 'out', 'source' => 'biometric']);
        $mark->setRelation('device', null);
        $employee->setRelation('attendances', collect([$mark]));

        $report = app(DailyAttendanceReportService::class)->build($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-02'), collect());

        $this->assertTrue($report['has_split']);
        $this->assertSame('Horario continuo', $report['days'][1]['schedule']);
        $this->assertSame(['SIN MARCADO', '—', '—', '17:02'], array_column($report['days'][1]['cells'], 'value'));
        $this->assertSame(1, $report['absence_days']);
        $this->assertSame(0.5, $report['abandonment_days']);
    }

    public function test_xlsx_has_daily_rows_colored_mark_cells_selected_metadata_and_literal_text(): void
    {
        $employee = $this->employee();
        $employee->position = '=HYPERLINK("https://example.com")';
        $service = app(DailyAttendanceReportService::class);
        $from = Carbon::parse('2026-09-01');
        $to = Carbon::parse('2026-09-02');
        $holiday = Holiday::factory()->make(['date' => '2026-09-02', 'name' => 'Feriado institucional']);
        $report = [...$service->build($employee, $from, $to, collect([$holiday])), 'metadata' => $service->metadata($employee, ['unit', 'position'])];
        $other = [...$report, 'employee' => clone $employee];
        $other['employee']->id = 2;
        $export = new DailyAttendanceWorkbook(collect([$report, $other]), $from, $to, ['schedule', 'late_minutes']);
        $path = tempnam(sys_get_temp_dir(), 'daily-report-');

        try {
            file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
            $row = array_search('2026-09-01', array_column($rows, 0), true) + 1;
            $holidayRow = array_search('2026-09-02', array_column($rows, 0), true) + 1;
            $positionRow = array_search('Cargo', array_column($rows, 0), true) + 1;

            $this->assertSame(2, $workbook->getSheetCount());
            $this->assertNotSame($workbook->getSheet(0)->getTitle(), $workbook->getSheet(1)->getTitle());
            $this->assertSame('BM', $sheet->getCell('D'.$row)->getValue());
            $this->assertSame('FFDC2626', $sheet->getStyle('D'.$row)->getFill()->getStartColor()->getARGB());
            $this->assertSame('FE', $sheet->getCell('D'.$holidayRow)->getValue());
            $this->assertSame('FFBE123C', $sheet->getStyle('D'.$holidayRow)->getFill()->getStartColor()->getARGB());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B'.$positionRow)->getDataType());
            $this->assertSame($employee->position, $sheet->getCell('B'.$positionRow)->getValue());
            $this->assertNotContains('Documento', array_column($rows, 0));
            $this->assertNotContains('Origen / biométrico', $rows[$row - 2]);
            $this->assertContains('Retraso registrado (min)', $rows[$row - 2]);
            $this->assertNotEmpty($sheet->getFreezePane());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_combined_xlsx_has_all_employees_on_one_sheet_with_colored_permissions(): void
    {
        $employee = $this->employee();
        $date = Carbon::parse('2026-09-01');
        $report = [...app(DailyAttendanceReportService::class)->build($employee, $date, $date, new EloquentCollection), 'metadata' => ['Código' => '002']];
        $other = [...$report, 'employee' => clone $employee];
        $other['employee']->id = 2;
        $other['employee']->first_name = 'OTRO EMPLEADO';
        $export = new DailyAttendanceWorkbook(collect([$report, $other]), $date, $date, ['schedule'], true);
        $path = tempnam(sys_get_temp_dir(), 'combined-report-');

        try {
            file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getSheet(0);
            $rows = $sheet->toArray();
            $this->assertSame(1, $workbook->getSheetCount());
            $this->assertSame('Todos los empleados', $sheet->getTitle());
            $employeeRows = array_keys(array_column($rows, 0), 'Empleado', true);
            $this->assertCount(2, $employeeRows);
            $this->assertSame($employee->full_name, $rows[$employeeRows[0]][1]);
            $this->assertSame($other['employee']->full_name, $rows[$employeeRows[1]][1]);
            foreach (array_keys(array_column($rows, 0), '2026-09-01', true) as $dayRow) {
                $this->assertSame('BM', $sheet->getCell('D'.($dayRow + 1))->getValue());
                $this->assertSame('FFDC2626', $sheet->getStyle('D'.($dayRow + 1))->getFill()->getStartColor()->getARGB());
            }
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_additional_marks_are_included_even_when_optional_columns_are_unchecked(): void
    {
        $employee = $this->employee();
        $employee->setRelation('leaveRequests', collect());
        $mark = Attendance::factory()->make(['checked_at' => '2026-09-01 10:15', 'type' => 'break_in', 'source' => 'biometric']);
        $mark->setRelation('device', null);
        $employee->setRelation('attendances', collect([$mark]));
        $date = Carbon::parse('2026-09-01');
        $report = [...app(DailyAttendanceReportService::class)->build($employee, $date, $date, new EloquentCollection), 'metadata' => []];

        $sheet = (new DailyAttendanceWorkbook(collect([$report]), $date, $date, []))->sheets()[0];
        $rows = $sheet->array();
        $headings = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'Fecha');
        $day = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === '2026-09-01');

        $this->assertTrue($report['has_additional']);
        $this->assertContains('Otras marcaciones', $headings);
        $this->assertContains('Inicio pausa: 10:15', $day);
        $this->assertNotContains('Observaciones', $headings);
    }

    public function test_invalid_period_and_unapproved_fields_are_rejected_before_querying(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1, 'is_active' => true]));

        $this->getJson(route('reports.index', ['from' => '2026-09-01', 'to' => '2026-08-01']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('reports.daily-export', ['report_fields' => ['password']]))->assertUnprocessable()->assertJsonValidationErrors('report_fields.0');
        $this->getJson(route('reports.index', ['from' => '2025-01-01', 'to' => '2026-09-01']))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_checklist_defaults_and_explicit_empty_selection_are_preserved(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $default = AttendanceReportRequest::create('/reports', 'GET', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        $default->setContainer($this->app);
        $default->setRedirector(app('redirect'));
        $default->setUserResolver(fn () => $user);
        $default->validateResolved();

        $this->assertSame(DailyAttendanceReportService::DefaultFields, $default->validated('report_fields'));
        $this->assertSame(DailyAttendanceReportService::DefaultColumns, $default->validated('extra_columns'));

        $custom = AttendanceReportRequest::create('/reports', 'GET', ['from' => '2026-09-01', 'to' => '2026-09-30', 'custom_fields' => 1]);
        $custom->setContainer($this->app);
        $custom->setRedirector(app('redirect'));
        $custom->setUserResolver(fn () => $user);
        $custom->validateResolved();

        $this->assertSame([], $custom->validated('report_fields'));
        $this->assertSame([], $custom->validated('extra_columns'));
    }

    #[TestWith(['audit'])]
    public function test_undefined_reports_cannot_download_an_attendance_workbook(string $reportType): void
    {
        $user = User::factory()->make(['id' => 1, 'is_active' => true]);

        $this->actingAs($user)->getJson(route('reports.daily-export', ['report_type' => $reportType]))
            ->assertUnprocessable()->assertJsonValidationErrors('report_type')
            ->assertJsonPath('errors.report_type.0', 'El contenido del reporte de Auditoría está pendiente de definición.');
    }

    public function test_invalid_report_types_and_layouts_are_rejected(): void
    {
        $user = User::factory()->make(['id' => 1, 'is_active' => true]);

        $this->actingAs($user)->getJson(route('reports.daily-export', ['report_type' => 'unknown', 'excel_layout' => 'unknown']))
            ->assertUnprocessable()->assertJsonValidationErrors(['report_type', 'excel_layout']);
    }

    public function test_absence_totals_exclude_holidays_non_working_days_permissions_and_partial_marks(): void
    {
        $employee = $this->employee();
        $mark = Attendance::factory()->make(['checked_at' => '2026-09-08 08:07', 'type' => 'in', 'late_minutes' => 7, 'source' => 'manual']);
        $mark->setRelation('device', null);
        $employee->setRelation('attendances', collect([$mark]));
        $holiday = Holiday::factory()->make(['date' => '2026-09-02', 'is_recurring' => false]);

        $report = app(DailyAttendanceReportService::class)->build($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-09'), collect([$holiday]));

        $this->assertSame(1, $report['absence_days']);
        $this->assertSame(2.0, $report['absence_discount_days']);
        $this->assertSame(7, $report['late_minutes']);
        $this->assertSame(1.5, $report['abandonment_days']);
        $this->assertSame(0.0, $report['unpaid_leave_days']);
    }

    public function test_attendance_totals_are_exported_before_the_legend_without_optional_minutes_column(): void
    {
        $employee = $this->employee();
        $employee->setRelation('leaveRequests', collect());
        $date = Carbon::parse('2026-09-01');
        $report = [...app(DailyAttendanceReportService::class)->build($employee, $date, $date, collect()), 'metadata' => []];

        $rows = (new DailyAttendanceSheet($report, $date, $date, []))->array();

        $labels = array_column($rows, 0);
        $minutesIndex = array_search('MINUTOS RETRASO', $labels, true);
        $absenceIndex = array_search('DÍAS FALTA', $labels, true);
        $this->assertSame(['MINUTOS RETRASO', 0], $rows[$minutesIndex]);
        $this->assertSame(['DÍAS FALTA', 1], $rows[$absenceIndex]);
        $discountIndex = array_search('DESCUENTO POR INASISTENCIA (DÍAS)', $labels, true);
        $this->assertSame(['DESCUENTO POR INASISTENCIA (DÍAS)', 2.0], $rows[$discountIndex]);
        $this->assertLessThan(array_search('LEYENDA', $labels, true), $discountIndex);
        foreach (['DÍAS ABANDONO', 'DESCUENTO POR ABANDONO (DÍAS)', 'DÍAS LICENCIA SIN GOCE (SGH)', 'DESCUENTO POR LICENCIA SGH (DÍAS)'] as $label) {
            $index = array_search($label, $labels, true);
            $this->assertSame([$label, 0.0], $rows[$index]);
            $this->assertLessThan(array_search('LEYENDA', $labels, true), $index);
        }
    }

    #[TestWith([0, 1, 0.0])]
    #[TestWith([1, 0, 1.5])]
    #[TestWith([2, 0, 1.0])]
    #[TestWith([3, 0, 0.5])]
    #[TestWith([4, 0, 0.0])]
    public function test_each_uncovered_omission_is_half_a_day_except_full_day_absences(int $markCount, int $absenceDays, float $abandonmentDays): void
    {
        $employee = $this->employee();
        $employee->setRelation('leaveRequests', collect());
        $marks = collect(['08:00', '12:00', '15:00', '19:00'])->take($markCount)->map(function (string $time, int $index): Attendance {
            $mark = Attendance::factory()->make(['checked_at' => '2026-09-01 '.$time, 'type' => $index % 2 === 0 ? 'in' : 'out']);
            $mark->setRelation('device', null);

            return $mark;
        });
        $employee->setRelation('attendances', $marks);
        $date = Carbon::parse('2026-09-01');

        $report = app(DailyAttendanceReportService::class)->build($employee, $date, $date, collect());

        $this->assertSame($absenceDays, $report['absence_days']);
        $this->assertSame($abandonmentDays, $report['abandonment_days']);
        $this->assertSame($abandonmentDays, $report['abandonment_discount_days']);
    }

    #[TestWith(['all_day', null, 'approved', 1.0, 0.0])]
    #[TestWith(['half_day', 'morning', 'approved', 0.5, 1.0])]
    #[TestWith(['half_day', 'afternoon', 'approved', 0.5, 1.0])]
    #[TestWith(['hours', null, 'approved', 0.0, 1.0])]
    #[TestWith(['all_day', null, 'pending', 0.0, 0.0])]
    #[TestWith(['all_day', null, 'rejected', 0.0, 0.0])]
    public function test_only_approved_sgh_counts_as_unpaid_leave(string $duration, ?string $period, string $status, float $unpaidDays, float $abandonmentDays): void
    {
        $employee = $this->employee($duration, $period, $status);
        $employee->leaveRequests->first()->leaveType->abbreviation = 'SGH';
        $date = Carbon::parse('2026-09-01');

        $report = app(DailyAttendanceReportService::class)->build($employee, $date, $date, collect());

        $this->assertSame($unpaidDays, $report['unpaid_leave_days']);
        $this->assertSame($unpaidDays, $report['unpaid_leave_discount_days']);
        $this->assertSame($abandonmentDays, $report['abandonment_days']);
    }

    public function test_overlapping_sgh_permissions_are_counted_once_and_holidays_are_excluded(): void
    {
        $employee = $this->employee();
        $leave = $employee->leaveRequests->first();
        $leave->leaveType->abbreviation = 'SGH';
        $employee->setRelation('leaveRequests', collect([$leave, clone $leave]));
        $date = Carbon::parse('2026-09-01');
        $service = app(DailyAttendanceReportService::class);

        $report = $service->build($employee, $date, $date, collect());
        $holidayReport = $service->build($employee, $date, $date, collect([Holiday::factory()->make(['date' => $date, 'is_recurring' => false])]));

        $this->assertSame(1.0, $report['unpaid_leave_days']);
        $this->assertSame(0.0, $holidayReport['unpaid_leave_days']);
        $this->assertSame(0.0, $holidayReport['abandonment_days']);
        $this->assertSame(0, $holidayReport['absence_days']);
    }

    public function test_hourly_sgh_is_prorated_over_working_hours_and_other_permissions_are_not_unpaid(): void
    {
        $employee = $this->employee('hours');
        $leave = $employee->leaveRequests->first();
        $leave->leaveType->abbreviation = 'SGH';
        $leave->starts_at = '08:00';
        $leave->ends_at = '10:00';
        $date = Carbon::parse('2026-09-01');
        $service = app(DailyAttendanceReportService::class);

        $report = $service->build($employee, $date, $date, collect());
        $leave->leaveType->abbreviation = 'BM';
        $paidReport = $service->build($employee, $date, $date, collect());

        $this->assertSame(0.25, $report['unpaid_leave_days']);
        $this->assertSame(0.0, $paidReport['unpaid_leave_days']);
    }

    #[TestWith(['2026-09-01', '2026-09-30', 30])]
    #[TestWith(['2026-10-01', '2026-10-31', 31])]
    #[TestWith(['2026-02-01', '2026-02-28', 28])]
    #[TestWith(['2024-02-01', '2024-02-29', 29])]
    #[TestWith(['2026-09-10', '2026-09-20', 11])]
    public function test_calendar_days_follow_the_selected_period(string $from, string $to, int $expectedDays): void
    {
        $employee = $this->employee();

        $report = app(DailyAttendanceReportService::class)->build($employee, Carbon::parse($from), Carbon::parse($to), collect());

        $this->assertSame($expectedDays, $report['calendar_days']);
    }

    private function employee(string $duration = 'all_day', ?string $period = null, string $status = 'approved'): Employee
    {
        $employee = Employee::factory()->make(['id' => 1, 'office_id' => null, 'work_unit_id' => 1, 'identity_document' => '1234567', 'first_name' => 'Ana', 'last_name' => 'Pérez']);
        $schedule = WorkSchedule::factory()->make(['name' => 'Horario discontinuo']);
        $schedule->setRelation('days', collect([2, 3])->map(fn (int $weekday) => WorkScheduleDay::factory()->make([
            'day_of_week' => $weekday, 'starts_at' => '08:00', 'ends_at' => '12:00', 'second_starts_at' => '15:00', 'second_ends_at' => '19:00', 'is_working_day' => true,
        ])));
        $leave = LeaveRequest::factory()->make(['id' => 1, 'employee_id' => 1, 'leave_type_id' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'duration_type' => $duration, 'half_day_period' => $period, 'starts_at' => '12:00', 'ends_at' => '15:00', 'status' => $status]);
        $leave->setRelation('leaveType', LeaveType::factory()->make(['id' => 1, 'name' => 'Baja médica', 'abbreviation' => 'BM', 'color' => '#DC2626']));
        $employee->setRelation('workSchedule', $schedule);
        $employee->setRelation('scheduleAssignments', collect());
        $employee->setRelation('attendances', collect());
        $employee->setRelation('leaveRequests', new EloquentCollection([$leave]));
        $employee->setRelation('workUnit', WorkUnit::factory()->make(['office_id' => 1, 'name' => 'Recursos Humanos']));
        $employee->setRelation('office', null);

        return $employee;
    }
}

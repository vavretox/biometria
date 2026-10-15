<?php

namespace Tests\Feature;

use App\Exports\PayrollWorkbook;
use App\Models\Employee;
use App\Models\Office;
use App\Services\PayrollReportService;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PayrollReportTest extends TestCase
{
    public function test_the_summary_has_one_row_per_employee_and_uses_attendance_totals(): void
    {
        $employee = Employee::factory()->make(['office_id' => null, 'work_unit_id' => null, 'budget_line' => '11700', 'item_number' => '00123', 'identity_document' => '000567', 'last_name' => 'ARAMAYO', 'maternal_last_name' => 'YAÑEZ', 'first_name' => 'MAURICIO', 'employment_observations' => '']);
        $employee->setRelation('office', Office::factory()->make(['name' => 'Edificio Seguridad Ciudadana']));
        $report = ['employee' => $employee, 'mark_count' => 2, 'calendar_days' => 30, 'late_minutes' => 6, 'absence_days' => 2, 'abandonment_days' => 0.5, 'unpaid_leave_days' => 1.0, 'absence_discount_days' => 4.0, 'abandonment_discount_days' => 0.5, 'unpaid_leave_discount_days' => 1.0, 'days' => collect([['observation' => 'Revisión', 'incidences' => 'BM · Baja médica']])];

        $rows = app(PayrollReportService::class)->rows(collect([$report]));

        $this->assertCount(1, $rows);
        $this->assertSame(['Edificio Seguridad Ciudadana', '11700', 1, '00123', '000567', 'ARAMAYO', 'YAÑEZ', 'MAURICIO', 28, 'S', 6, 2, 0.5, 1.0, 5.5, 0.0, 4.0, 0.5, 1.0], $rows->first());
        $this->assertSame('N', app(PayrollReportService::class)->rows(collect([[...$report, 'mark_count' => 0]]))->first()[9]);
    }

    public function test_lateness_is_penalized_by_month_instead_of_combining_the_whole_range(): void
    {
        $employee = Employee::factory()->make(['office_id' => null, 'work_unit_id' => null]);
        $employee->setRelation('office', null);
        $report = ['employee' => $employee, 'mark_count' => 2, 'calendar_days' => 61, 'late_minutes' => 120, 'absence_days' => 0, 'abandonment_days' => 0.0, 'unpaid_leave_days' => 0.0, 'absence_discount_days' => 0.0, 'abandonment_discount_days' => 0.0, 'unpaid_leave_discount_days' => 0.0, 'days' => collect([
            ['date' => CarbonImmutable::parse('2026-09-01'), 'late_minutes' => 60],
            ['date' => CarbonImmutable::parse('2026-10-01'), 'late_minutes' => 60],
        ])];

        $row = app(PayrollReportService::class)->rows(collect([$report]))->first();

        $this->assertSame(120, $row[10]);
        $this->assertSame(2.0, $row[15]);
        $this->assertSame(2.0, $row[14]);
    }

    public function test_a_month_above_120_minutes_is_flagged_for_review(): void
    {
        $employee = Employee::factory()->make(['office_id' => null, 'work_unit_id' => null]);
        $employee->setRelation('office', null);
        $report = ['employee' => $employee, 'mark_count' => 2, 'calendar_days' => 31, 'late_minutes' => 121, 'absence_days' => 1, 'abandonment_days' => 0.5, 'unpaid_leave_days' => 1.0, 'absence_discount_days' => 2.0, 'abandonment_discount_days' => 0.5, 'unpaid_leave_discount_days' => 1.0, 'days' => collect([
            ['date' => CarbonImmutable::parse('2026-10-01'), 'late_minutes' => 121],
        ])];

        $row = app(PayrollReportService::class)->rows(collect([$report]))->first();

        $this->assertSame('Pendiente: 121+ minutos', $row[15]);
        $this->assertSame('Pendiente: 121+ minutos', $row[14]);
        $this->assertSame([1, 0.5, 1.0], array_slice($row, 11, 3));
        $this->assertSame([2.0, 0.5, 1.0], array_slice($row, 16, 3));
    }

    public function test_the_payroll_excel_preserves_identifiers_and_zero_minutes(): void
    {
        $row = ['Edificio', '11700', 1, '00123', '000567', 'Apellido', '', '=NOMBRE', 31, 'N', 0, 0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $date = CarbonImmutable::parse('2026-10-01');
        $export = new PayrollWorkbook(collect([$row]), $date, $date, ['payroll_location' => 'Seguridad Ciudadana', 'payroll_contact' => 'Encargada']);
        $path = tempnam(sys_get_temp_dir(), 'payroll-');

        try {
            file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getSheet(0);
            $this->assertSame(1, $workbook->getSheetCount());
            $this->assertSame('Seguridad Ciudadana', $sheet->getCell('B3')->getValue());
            $this->assertSame('00123', $sheet->getCell('D10')->getValue());
            $this->assertSame('000567', $sheet->getCell('E10')->getValue());
            $this->assertSame('=NOMBRE', $sheet->getCell('H10')->getValue());
            $this->assertSame('s', $sheet->getCell('H10')->getDataType());
            $this->assertSame(0, $sheet->getCell('K10')->getValue());
            $this->assertSame(0.0, $sheet->getCell('O10')->getValue());
            $this->assertSame('Total minutos retraso', $sheet->getCell('K9')->getValue());
            $this->assertSame('S', $sheet->getHighestColumn());
            $this->assertNotContains('Observaciones', $sheet->rangeToArray('A9:S9')[0]);
            $this->assertNotContains('Novedades', $sheet->rangeToArray('A9:S9')[0]);
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertCount(2, (new PayrollWorkbook(collect([$row, $row]), $date, $date, [], false))->sheets());
    }
}

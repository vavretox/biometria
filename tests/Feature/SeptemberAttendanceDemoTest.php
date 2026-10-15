<?php

namespace Tests\Feature;

use App\Exports\DailyAttendanceWorkbook;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use App\Services\DailyAttendanceReportService;
use App\Services\PayrollReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Database\Seeders\SeptemberAttendanceDemoSeeder;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SeptemberAttendanceDemoTest extends TestCase
{
    #[TestWith([false, 3])]
    #[TestWith([true, 1])]
    public function test_three_employees_have_monthly_totals_and_all_permission_types_in_both_excel_layouts(bool $singleSheet, int $sheetCount): void
    {
        $this->freezeTime();
        $from = Carbon::parse('2026-09-01');
        $to = Carbon::parse('2026-09-30');
        $service = app(DailyAttendanceReportService::class);
        $reports = collect(range(0, 2))->map(function (int $index) use ($service, $from, $to): array {
            $employee = $this->employee($index);

            return [...$service->build($employee, $from, $to, collect([Holiday::factory()->make(['date' => '2026-09-24', 'is_recurring' => false])])), 'metadata' => []];
        });
        $export = new DailyAttendanceWorkbook($reports, $from, $to, ['schedule', 'late_minutes'], $singleSheet);
        $path = tempnam(sys_get_temp_dir(), 'september-report-');

        try {
            file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
            $workbook = IOFactory::load($path);
            $this->assertSame($sheetCount, $workbook->getSheetCount());
            $allRows = collect($workbook->getAllSheets())->flatMap(fn ($sheet): array => $sheet->toArray(null, false, false, false));
            $this->assertCount(90, $allRows->filter(fn (array $row): bool => preg_match('/^2026-09-\d{2}$/', (string) ($row[0] ?? '')) === 1));
            $this->assertSame([30, 60, 125], $allRows->where(0, 'MINUTOS RETRASO')->pluck(1)->all());
            $this->assertSame([3, 3, 3], $allRows->where(0, 'DÍAS FALTA')->pluck(1)->all());
            $this->assertSame([6.0, 6.0, 6.0], $allRows->where(0, 'DESCUENTO POR INASISTENCIA (DÍAS)')->pluck(1)->all());
            $this->assertSame([3.0, 3.0, 3.0], $allRows->where(0, 'DÍAS ABANDONO')->pluck(1)->all());
            $this->assertSame([2.25, 2.25, 2.25], $allRows->where(0, 'DÍAS LICENCIA SIN GOCE (SGH)')->pluck(1)->all());
            $this->assertSame([2.25, 2.25, 2.25], $allRows->where(0, 'DESCUENTO POR LICENCIA SGH (DÍAS)')->pluck(1)->all());
            foreach (['VL', 'BM', 'AP', 'CO', 'OT', 'MA', 'CS', 'RE', 'VI', 'NA', 'PA', 'MA2', 'DE', 'SGH'] as $abbreviation) {
                $this->assertTrue($allRows->contains(fn (array $row): bool => in_array($abbreviation, $row, true)), 'No se exportó el permiso '.$abbreviation);
            }
            $this->assertTrue($allRows->contains(fn (array $row): bool => in_array('SIN MARCADO', $row, true)));
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    #[TestWith([0, 11.25, 0.0])]
    #[TestWith([1, 12.25, 1.0])]
    #[TestWith([2, 'Pendiente: 121+ minutos', 'Pendiente: 121+ minutos'])]
    public function test_payroll_summarizes_the_monthly_attendance_report(int $employeeIndex, float|string $totalDiscount, float|string $latenessDiscount): void
    {
        $employee = $this->employee($employeeIndex);
        $report = app(DailyAttendanceReportService::class)->build($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), collect([
            Holiday::factory()->make(['date' => '2026-09-24', 'is_recurring' => false]),
        ]));

        $row = app(PayrollReportService::class)->rows(collect([$report]))->first();
        $summary = array_combine(PayrollReportService::Headings, $row);

        $this->assertSame(27, $summary['Días trabajados']);
        $this->assertSame(3, $summary['Faltas (días)']);
        $this->assertSame(3.0, $summary['Abandonos (días)']);
        $this->assertSame(2.25, $summary['Licencia sin goce de haber (días)']);
        $this->assertSame(6.0, $summary['Descuento por inasistencia (días)']);
        $this->assertSame(3.0, $summary['Descuento por abandono (días)']);
        $this->assertSame(2.25, $summary['Descuento por licencia sin goce (días)']);
        $this->assertSame($latenessDiscount, $summary['Descuento por atraso (días)']);
        $this->assertSame($totalDiscount, $summary['Total días a descontar']);
        $this->assertArrayNotHasKey('Observaciones', $summary);
        $this->assertArrayNotHasKey('Novedades', $summary);
    }

    private function employee(int $index): Employee
    {
        $employee = Employee::factory()->make(['id' => $index + 1, 'office_id' => null, 'work_unit_id' => null]);
        $schedule = WorkSchedule::factory()->make(['id' => 1, 'name' => 'Horario de prueba']);
        $schedule->setRelation('days', collect(range(1, 5))->map(fn (int $weekday): WorkScheduleDay => WorkScheduleDay::factory()->make([
            'day_of_week' => $weekday, 'starts_at' => '08:00', 'ends_at' => '12:00', 'second_starts_at' => '15:00', 'second_ends_at' => '19:00', 'is_working_day' => true,
        ])));
        $types = collect(['VL', 'BM', 'AP', 'CO', 'OT', 'MA', 'CS', 'RE', 'VI', 'NA', 'PA', 'MA2', 'DE', 'SGH'])->map(fn (string $code, int $offset): LeaveType => LeaveType::factory()->make(['id' => $offset + 1, 'name' => 'Permiso '.$code, 'code' => 'permission_'.$offset, 'abbreviation' => $code]));
        $scenarios = (new SeptemberAttendanceDemoSeeder)->scenarios($types, $index);
        $scenarioIndex = 0;
        $marks = collect();
        $leaves = collect();
        foreach (CarbonPeriod::create('2026-09-01', '2026-09-30') as $date) {
            if ($date->isWeekend() || $date->toDateString() === '2026-09-24') {
                continue;
            }
            $scenario = $scenarios[$scenarioIndex++] ?? ['omissions' => 0, 'late_minutes' => 0, 'leave' => null];
            $specification = $scenario['leave'];
            $times = collect(['08:00', '12:00', '15:00', '19:00']);
            if ($specification !== null) {
                $leave = LeaveRequest::factory()->make([
                    'employee_id' => $employee->id, 'leave_type_id' => $specification['type']->id, 'type' => $specification['type']->code,
                    'starts_on' => $date->toDateString(), 'ends_on' => $date->toDateString(),
                    'duration_type' => $specification['duration'], 'half_day_period' => $specification['period'],
                    'starts_at' => $specification['duration'] === 'hours' ? '08:00' : null,
                    'ends_at' => $specification['duration'] === 'hours' ? '10:00' : null, 'status' => $specification['status'],
                ]);
                $leave->setRelation('leaveType', $specification['type']);
                $leaves->push($leave);
                if ($specification['status'] === 'approved') {
                    $times = match ($specification['duration']) {
                        'all_day' => collect(),
                        'half_day' => $specification['period'] === 'morning' ? $times->slice(2) : $times->take(2),
                        default => $times->slice(1),
                    };
                }
            }
            $remaining = $scenario['omissions'] === -1 ? 0 : max(0, $times->count() - $scenario['omissions']);
            foreach ($times->take($remaining) as $slotIndex => $time) {
                $minutes = $slotIndex === 0 ? $scenario['late_minutes'] : 0;
                $mark = Attendance::factory()->make([
                    'employee_id' => $employee->id, 'checked_at' => Carbon::parse($date->toDateString().' '.$time)->addMinutes($minutes),
                    'type' => $slotIndex % 2 === 0 ? 'in' : 'out', 'status' => $minutes > 0 ? 'late' : 'normal',
                    'late_minutes' => $minutes, 'source' => 'manual',
                ]);
                $mark->setRelation('device', null);
                $marks->push($mark);
            }
        }
        $employee->setRelation('workSchedule', $schedule);
        $employee->setRelation('scheduleAssignments', collect());
        $employee->setRelation('attendances', $marks);
        $employee->setRelation('leaveRequests', $leaves);
        $employee->setRelation('office', null);
        $employee->setRelation('workUnit', null);

        return $employee;
    }
}

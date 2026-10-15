<?php

namespace App\Http\Controllers;

use App\Exports\DailyAttendanceWorkbook;
use App\Exports\PayrollWorkbook;
use App\Exports\UnitAttendanceExport;
use App\Http\Requests\AttendanceReportRequest;
use App\Http\Requests\ExportUnitAttendanceReportRequest;
use App\Models\Holiday;
use App\Models\WorkUnit;
use App\Services\DailyAttendanceReportService;
use App\Services\PayrollReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function __invoke(ExportUnitAttendanceReportRequest $request): BinaryFileResponse
    {
        $data = $request->validated();
        $workUnit = WorkUnit::findOrFail($data['work_unit_id']);
        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $to = CarbonImmutable::parse($data['to'])->endOfDay();
        $fileName = 'reporte-'.Str::slug($workUnit->name).'-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.xlsx';

        return Excel::download(new UnitAttendanceExport(
            workUnit: $workUnit,
            from: $from,
            to: $to,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            scheduleType: $data['schedule_type'] ?? null,
        ), $fileName);
    }

    public function daily(AttendanceReportRequest $request, DailyAttendanceReportService $reportService, PayrollReportService $payrollService): BinaryFileResponse
    {
        $filters = $request->validated();
        if (($filters['report_type'] ?? 'attendance') === 'audit') {
            throw ValidationException::withMessages(['report_type' => 'El contenido del reporte de Auditoría está pendiente de definición.']);
        }
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        $employees = $reportService->employees($filters, $from, $to);
        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['employee_id' => 'No hay empleados para los filtros seleccionados.']);
        }
        $holidays = Holiday::orderBy('date')->get();
        $reports = $employees->map(function ($employee) use ($filters, $from, $to, $holidays, $reportService): array {
            return [...$reportService->build($employee, $from, $to, $holidays, $filters['schedule_type'] ?? null), 'metadata' => $reportService->metadata($employee, $filters['report_fields'])];
        });
        if (($filters['report_type'] ?? 'attendance') === 'payroll') {
            return Excel::download(new PayrollWorkbook(
                $payrollService->rows($reports), $from, $to,
                array_intersect_key($filters, array_flip(['payroll_location', 'payroll_direction', 'payroll_phone', 'payroll_contact'])),
                ($filters['excel_layout'] ?? 'single_sheet') === 'single_sheet',
            ), 'planillas-'.$from->toDateString().'-'.$to->toDateString().'.xlsx');
        }
        $name = $employees->count() === 1 ? Str::slug($employees->first()->full_name) : 'empleados';

        return Excel::download(new DailyAttendanceWorkbook($reports, $from, $to, $filters['extra_columns'], ($filters['excel_layout'] ?? 'per_employee') === 'single_sheet'), 'asistencia-'.$name.'-'.$from->toDateString().'-'.$to->toDateString().'.xlsx');
    }
}

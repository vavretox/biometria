<?php

namespace App\Http\Controllers;

use App\Exports\UnitAttendanceExport;
use App\Http\Requests\ExportUnitAttendanceReportRequest;
use App\Models\WorkUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
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
}

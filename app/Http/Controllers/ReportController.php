<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceReportRequest;
use App\Models\Attendance;
use App\Models\Biometric\BiometricDevice;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkUnit;
use App\Services\DailyAttendanceReportService;
use App\Services\PayrollReportService;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(AttendanceReportRequest $request, DailyAttendanceReportService $dailyReportService, PayrollReportService $payrollService): View
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfMonth();

        if ($to->isBefore($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $scheduleType = in_array($request->string('schedule_type')->toString(), ['continuous', 'split'], true)
            ? $request->string('schedule_type')->toString()
            : null;

        $reportType = $request->validated('report_type') ?? 'attendance';
        $reportRequested = $request->reportRequested && $reportType !== 'audit';
        $employees = $reportRequested
            ? $dailyReportService->employees($request->validated(), $from, $to)
            : new Collection;

        $employees->each(function (Employee $employee) use ($scheduleType): void {
            $attendances = $employee->attendances
                ->when($scheduleType, function ($attendances, $type) use ($employee) {
                    return $attendances->filter(function (Attendance $attendance) use ($employee, $type): bool {
                        $schedule = $employee->attendanceScheduleForDate($attendance->checked_at);
                        $isSplit = $schedule?->days->contains(fn ($day): bool => $day->second_starts_at !== null) ?? false;

                        return $schedule !== null && ($type === 'split' ? $isSplit : ! $isSplit);
                    });
                });

            $employee->setAttribute('attendance_count', $attendances->count());
            $employee->setAttribute('late_count', $attendances->where('status', 'late')->count());
            $employee->setAttribute('late_minutes_total', $attendances->sum('late_minutes'));
        });

        $scheduleHistory = $employees->flatMap(function (Employee $employee) use ($from, $scheduleType, $to) {
            return $employee->scheduleAssignments
                ->filter(function ($assignment) use ($scheduleType): bool {
                    if ($scheduleType === null) {
                        return true;
                    }

                    $isSplit = $assignment->workSchedule->days->contains(fn ($day): bool => $day->second_starts_at !== null);

                    return $scheduleType === 'split' ? $isSplit : ! $isSplit;
                })
                ->map(function ($assignment) use ($employee, $from, $to): array {
                    $schedule = $assignment->workSchedule;
                    $day = $schedule->days->first();
                    $isSplit = $schedule->days->contains(fn ($day): bool => $day->second_starts_at !== null);
                    $periodStarts = $assignment->starts_on->greaterThan($from) ? $assignment->starts_on : $from;
                    $periodEnds = $assignment->ends_on && $assignment->ends_on->lessThan($to) ? $assignment->ends_on : $to;

                    return [
                        'employee' => $employee,
                        'schedule' => $schedule,
                        'type' => $isSplit ? 'Discontinuo' : 'Continuo',
                        'hours' => $day
                            ? substr($day->starts_at, 0, 5).'–'.substr($day->ends_at, 0, 5)
                                .($day->second_starts_at ? ' / '.substr($day->second_starts_at, 0, 5).'–'.substr($day->second_ends_at, 0, 5) : '')
                            : 'Sin detalle',
                        'starts_on' => $periodStarts,
                        'ends_on' => $periodEnds,
                    ];
                });
        })->values();

        $holidayDefinitions = Holiday::orderBy('date')->get();
        $holidays = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->flatMap(fn ($date) => $holidayDefinitions
                ->filter(fn (Holiday $holiday): bool => $holiday->date->isSameDay($date)
                    || ($holiday->is_recurring && $holiday->date->format('m-d') === $date->format('m-d')))
                ->map(fn (Holiday $holiday): array => ['holiday' => $holiday, 'date' => $date->copy()]))
            ->values();

        $reports = $employees->take(10)->map(fn (Employee $employee): array => $dailyReportService->build($employee, $from, $to, $holidayDefinitions, $scheduleType));
        $fields = $request->validated('report_fields');
        $columns = $request->validated('extra_columns');
        $reports = $reports->map(fn (array $report): array => [...$report, 'metadata' => $dailyReportService->metadata($report['employee'], $fields)]);

        return view('reports.index', [
            'reportRequested' => $reportRequested,
            'reportType' => $reportType,
            'excelLayout' => $request->validated('excel_layout') ?? ($reportType === 'payroll' ? 'single_sheet' : 'per_employee'),
            'payrollRows' => $reportType === 'payroll' ? $payrollService->rows($reports) : collect(),
            'employees' => $employees,
            'dailyReports' => $reports,
            'fieldOptions' => DailyAttendanceReportService::Fields,
            'columnOptions' => DailyAttendanceReportService::Columns,
            'selectedFields' => $fields,
            'selectedColumns' => $columns,
            'devices' => BiometricDevice::orderBy('device_name')->get(['id', 'device_name']),
            'employeeOptions' => Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name', 'maternal_last_name', 'work_unit_id']),
            'from' => $from,
            'to' => $to,
            'scheduleType' => $scheduleType,
            'scheduleHistory' => $scheduleHistory,
            'holidays' => $holidays,
            'leaveTypes' => $employees->flatMap->leaveRequests
                ->pluck('leaveType')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'workUnits' => WorkUnit::orderBy('name')->get(),
            'totalMarks' => $employees->sum('attendance_count'),
        ]);
    }
}

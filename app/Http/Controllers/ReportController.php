<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkUnit;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfMonth();

        if ($to->isBefore($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $scheduleType = in_array($request->string('schedule_type')->toString(), ['continuous', 'split'], true)
            ? $request->string('schedule_type')->toString()
            : null;

        $overlapsPeriod = fn ($query) => $query
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()));

        $employees = Employee::with([
            'workUnit:id,name',
            'attendances' => fn ($query) => $query->whereBetween('checked_at', [$from, $to])->oldest('checked_at'),
            'scheduleAssignments' => fn ($query) => $overlapsPeriod($query)
                ->with('workSchedule.days')
                ->oldest('starts_on'),
            'leaveRequests' => fn ($query) => $query
                ->where('status', 'approved')
                ->whereDate('starts_on', '<=', $to)
                ->whereDate('ends_on', '>=', $from)
                ->with('leaveType:id,name,abbreviation,color'),
        ])
            ->when($request->integer('work_unit_id'), fn ($query, $unitId) => $query->where('work_unit_id', $unitId))
            ->when($request->integer('employee_id'), fn ($query, $employeeId) => $query->whereKey($employeeId))
            ->when($scheduleType, fn ($query, $type) => $query->whereHas(
                'scheduleAssignments',
                fn ($query) => $overlapsPeriod($query)->whereHas(
                    'workSchedule',
                    fn ($query) => $type === 'split'
                        ? $query->whereHas('days', fn ($query) => $query->whereNotNull('second_starts_at'))
                        : $query->whereDoesntHave('days', fn ($query) => $query->whereNotNull('second_starts_at')),
                ),
            ))
            ->withCount([
                'leaveRequests as approved_leave_count' => fn ($query) => $query->where('status', 'approved')->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from),
            ])
            ->orderBy('last_name')->get();

        $employees->each(function (Employee $employee) use ($scheduleType): void {
            $attendances = $employee->attendances
                ->when($scheduleType, function ($attendances, $type) use ($employee) {
                    return $attendances->filter(function (Attendance $attendance) use ($employee, $type): bool {
                        $schedule = $employee->scheduleForDate($attendance->checked_at);
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

        return view('reports.index', [
            'employees' => $employees,
            'employeeOptions' => Employee::where('is_active', true)->orderBy('last_name')->get(),
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

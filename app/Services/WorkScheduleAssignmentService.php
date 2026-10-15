<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkScheduleAssignmentService
{
    public function assign(Employee $employee, WorkSchedule $schedule, CarbonImmutable $startsOn, ?CarbonImmutable $endsOn, ?int $assignedBy, array $weekdays = [1, 2, 3, 4, 5, 6, 7]): void
    {
        DB::transaction(function () use ($employee, $schedule, $startsOn, $endsOn, $assignedBy, $weekdays): void {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $overlappingAssignments = $employee->scheduleAssignments()
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $startsOn->toDateString()))
                ->when($endsOn, fn ($query) => $query->whereDate('starts_on', '<=', $endsOn->toDateString()))
                ->lockForUpdate()
                ->get();

            $boundaries = $overlappingAssignments->pluck('starts_on')->push($startsOn);
            foreach ($boundaries as $boundary) {
                $date = $boundary->lt($startsOn) ? $startsOn : $boundary;
                $scheduleIds = $overlappingAssignments
                    ->filter(fn (WorkScheduleAssignment $assignment): bool => $assignment->starts_on->lte($date)
                        && ($assignment->ends_on === null || $assignment->ends_on->gte($date)))
                    ->pluck('work_schedule_id')->push($schedule->id)->unique();
                if ($scheduleIds->count() > 2) {
                    throw ValidationException::withMessages([
                        'schedule_limit' => $employee->full_name.' ya tiene dos horarios en el periodo indicado. No se puede asignar un tercer horario.',
                    ]);
                }
            }

            foreach ($overlappingAssignments as $assignment) {
                $originalWeekdays = $assignment->weekdays ?? range(1, 7);
                if (array_intersect($originalWeekdays, $weekdays) === []) {
                    continue;
                }
                $remainingWeekdays = array_values(array_diff($originalWeekdays, $weekdays));
                $originalStart = $assignment->starts_on;

                $originalEnd = $assignment->ends_on
                    ? CarbonImmutable::parse($assignment->ends_on)
                    : null;
                $hasLeftSegment = $assignment->starts_on->lt($startsOn);
                $hasRightSegment = $endsOn !== null
                    && ($originalEnd === null || $originalEnd->gt($endsOn));

                if ($hasLeftSegment) {
                    $assignment->update(['ends_on' => $startsOn->subDay()]);
                } else {
                    $assignment->delete();
                }

                if ($remainingWeekdays !== []) {
                    WorkScheduleAssignment::create([
                        'employee_id' => $employee->id,
                        'work_schedule_id' => $assignment->work_schedule_id,
                        'starts_on' => $originalStart->gt($startsOn) ? $originalStart : $startsOn,
                        'ends_on' => $endsOn === null ? $originalEnd : ($originalEnd === null || $originalEnd->gt($endsOn) ? $endsOn : $originalEnd),
                        'assigned_by' => $assignment->assigned_by,
                        'weekdays' => $remainingWeekdays,
                    ]);
                }

                if ($hasRightSegment) {
                    WorkScheduleAssignment::create([
                        'employee_id' => $employee->id,
                        'work_schedule_id' => $assignment->work_schedule_id,
                        'starts_on' => $endsOn->addDay(),
                        'ends_on' => $originalEnd,
                        'assigned_by' => $assignment->assigned_by,
                        'weekdays' => $originalWeekdays,
                    ]);
                }
            }

            WorkScheduleAssignment::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $schedule->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'assigned_by' => $assignedBy,
                'weekdays' => $weekdays,
            ]);

            $currentAssignment = $employee->scheduleAssignments()
                ->whereDate('starts_on', '<=', today())
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                ->latest('starts_on')
                ->get()
                ->first(fn (WorkScheduleAssignment $assignment): bool => in_array(today()->dayOfWeekIso, $assignment->weekdays ?? range(1, 7), true));

            $employee->update(['work_schedule_id' => $currentAssignment?->work_schedule_id]);
        });
    }
}

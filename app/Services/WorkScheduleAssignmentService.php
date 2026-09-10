<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkScheduleAssignmentService
{
    public function assign(Employee $employee, WorkSchedule $schedule, CarbonImmutable $startsOn, ?CarbonImmutable $endsOn, ?int $assignedBy): void
    {
        DB::transaction(function () use ($employee, $schedule, $startsOn, $endsOn, $assignedBy): void {
            $overlappingAssignments = $employee->scheduleAssignments()
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $startsOn->toDateString()))
                ->when($endsOn, fn ($query) => $query->whereDate('starts_on', '<=', $endsOn->toDateString()))
                ->lockForUpdate()
                ->get();

            foreach ($overlappingAssignments as $assignment) {
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

                if ($hasRightSegment) {
                    WorkScheduleAssignment::create([
                        'employee_id' => $employee->id,
                        'work_schedule_id' => $assignment->work_schedule_id,
                        'starts_on' => $endsOn->addDay(),
                        'ends_on' => $originalEnd,
                        'assigned_by' => $assignment->assigned_by,
                    ]);
                }
            }

            WorkScheduleAssignment::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $schedule->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'assigned_by' => $assignedBy,
            ]);

            $currentAssignment = $employee->scheduleAssignments()
                ->whereDate('starts_on', '<=', today())
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                ->latest('starts_on')
                ->first();

            $employee->update(['work_schedule_id' => $currentAssignment?->work_schedule_id]);
        });
    }
}

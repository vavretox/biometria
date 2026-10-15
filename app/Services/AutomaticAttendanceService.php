<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AutomaticAttendanceService
{
    /** @return array{employees: int, marks: int, skipped: int} */
    public function generate(CarbonInterface $date): array
    {
        $date = CarbonImmutable::parse($date->toDateString());

        if ($this->isHoliday($date)) {
            return ['employees' => 0, 'marks' => 0, 'skipped' => 0];
        }

        $employees = Employee::query()->where('attendance_method', 'automatic')->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $date))
            ->where(fn ($query) => $query->whereNull('employment_end_date')->orWhereDate('employment_end_date', '>=', $date))
            ->with([
                'workSchedule.days',
                'scheduleAssignments' => fn ($query) => $query->whereDate('starts_on', '<=', $date)
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))->with('workSchedule.days'),
                'leaveRequests' => fn ($query) => $query->where('status', 'approved')
                    ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date),
                'attendances' => fn ($query) => $query->whereDate('checked_at', $date),
            ])->orderBy('id')->get();
        $marks = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            if ($employee->leaveRequests->isNotEmpty()) {
                $skipped++;

                continue;
            }

            $schedule = $employee->attendanceScheduleForDate($date);
            $day = $schedule?->days->firstWhere('day_of_week', $date->isoWeekday());

            if (! $day?->is_working_day) {
                $skipped++;

                continue;
            }

            foreach ([[$day->starts_at, 'in'], [$day->ends_at, 'out'], [$day->second_starts_at, 'in'], [$day->second_ends_at, 'out']] as [$time, $type]) {
                if ($time === null) {
                    continue;
                }

                $checkedAt = $date->setTimeFromTimeString($time);
                Attendance::firstOrCreate(
                    ['employee_id' => $employee->id, 'checked_at' => $checkedAt],
                    ['type' => $type, 'status' => 'normal', 'late_minutes' => 0, 'source' => 'automatic', 'raw_data' => ['generated_for' => $date->toDateString()]],
                );
                $marks++;
            }
        }

        return ['employees' => $employees->count(), 'marks' => $marks, 'skipped' => $skipped];
    }

    private function isHoliday(CarbonInterface $date): bool
    {
        return Holiday::query()->whereDate('date', $date)->orWhere(fn ($query) => $query
            ->where('is_recurring', true)->whereMonth('date', $date->month)->whereDay('date', $date->day))->exists();
    }
}

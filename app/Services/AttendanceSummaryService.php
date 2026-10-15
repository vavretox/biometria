<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendanceSummaryService
{
    /** @return array{employee: Employee, days: Collection, marked_count: int, expected_count: int, leave_count: int, covered_count: int} */
    public function summarize(Employee $employee, Carbon $from, Carbon $to): array
    {
        $marksByDate = $employee->attendances->groupBy(fn (Attendance $mark): string => $mark->checked_at->toDateString());
        $days = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($employee, $marksByDate): array {
                $marks = $marksByDate->get($date->toDateString(), collect())->sortBy('checked_at')->values();
                $schedule = $employee->attendanceScheduleForDate($date);
                $scheduleDay = $schedule?->days->firstWhere('day_of_week', $date->isoWeekday());
                $expectedTimes = $scheduleDay && $scheduleDay->is_working_day
                    ? collect([$scheduleDay->starts_at, $scheduleDay->ends_at, $scheduleDay->second_starts_at, $scheduleDay->second_ends_at])->filter()->values()
                    : collect();
                $labels = $expectedTimes->count() === 4
                    ? ['Entrada', 'Salida mediodía', 'Retorno', 'Salida']
                    : ['Entrada', 'Salida'];
                $slots = collect(range(0, max(2, $expectedTimes->count()) - 1))->map(fn (int $index): array => [
                    'label' => $labels[$index] ?? 'Marcación adicional',
                    'type' => $index % 2 === 0 ? 'in' : 'out',
                    'expected_time' => $expectedTimes->has($index) ? mb_substr($expectedTimes[$index], 0, 5) : null,
                    'mark' => null,
                    'difference' => null,
                    'leave' => null,
                ]);

                foreach ($marks as $mark) {
                    $available = $slots->filter(fn (array $slot): bool => $slot['mark'] === null && $slot['type'] === $mark->type);
                    $index = $available->sortBy(function (array $slot) use ($date, $mark): float {
                        return $slot['expected_time']
                            ? abs(Carbon::parse($date->toDateString().' '.$slot['expected_time'])->diffInMinutes($mark->checked_at, false))
                            : 0;
                    })->keys()->first();
                    if ($index === null) {
                        $slots->push(['label' => ['in' => 'Entrada', 'out' => 'Salida', 'break_in' => 'Inicio pausa', 'break_out' => 'Fin pausa'][$mark->type], 'type' => $mark->type, 'expected_time' => null, 'mark' => $mark, 'difference' => null, 'leave' => null]);
                    } else {
                        $slot = $slots[$index];
                        $slot['mark'] = $mark;
                        $slot['difference'] = $slot['expected_time']
                            ? (int) Carbon::parse($date->toDateString().' '.$slot['expected_time'])->diffInMinutes($mark->checked_at, false)
                            : null;
                        $slots[$index] = $slot;
                    }
                }

                $leaves = $employee->leaveRequests->filter(fn ($leave): bool => $leave->starts_on->toDateString() <= $date->toDateString() && $leave->ends_on->toDateString() >= $date->toDateString());
                $approvedLeaves = $leaves->where('status', 'approved');
                $slots = $slots->map(function (array $slot, int $index) use ($approvedLeaves, $date, $expectedTimes): array {
                    $slot['leave'] = $approvedLeaves->first(fn (LeaveRequest $leave): bool => $this->coversSlot($leave, $slot['expected_time'], $index, $expectedTimes->count(), $date));

                    return $slot;
                });
                $coveredCount = $slots->filter(fn (array $slot): bool => $slot['leave'] !== null && $slot['mark'] === null)->count();
                $markedCount = $marks->count();
                $expectedCount = $expectedTimes->count();
                $completedCount = $slots->filter(fn (array $slot): bool => $slot['expected_time'] !== null && ($slot['mark'] !== null || $slot['leave'] !== null))->count();

                return compact('date', 'schedule', 'slots', 'leaves', 'markedCount', 'expectedCount', 'completedCount', 'coveredCount');
            });

        return [
            'employee' => $employee, 'days' => $days,
            'marked_count' => $days->sum('markedCount'), 'expected_count' => $days->sum('expectedCount'),
            'covered_count' => $days->sum('coveredCount'),
            'leave_count' => $days->sum(fn (array $day): int => $day['leaves']->count()),
        ];
    }

    private function coversSlot(LeaveRequest $leave, ?string $expectedTime, int $index, int $expectedCount, Carbon $date): bool
    {
        if ($leave->duration_type === 'all_day') {
            return true;
        }

        if ($expectedTime === null) {
            return false;
        }

        if ($leave->duration_type === 'half_day') {
            $isMorning = $expectedCount === 4 ? $index < 2 : $expectedTime < '13:00';

            return $leave->half_day_period === ($isMorning ? 'morning' : 'afternoon');
        }

        if ($leave->duration_type === 'hours' && $leave->starts_at && $leave->ends_at) {
            $expectedAt = $date->copy()->setTimeFromTimeString($expectedTime);
            $startsAt = $leave->starts_on->copy()->setTimeFromTimeString($leave->starts_at);
            $endsAt = $leave->ends_on->copy()->setTimeFromTimeString($leave->ends_at);

            return $expectedAt->betweenIncluded($startsAt, $endsAt);
        }

        return false;
    }
}

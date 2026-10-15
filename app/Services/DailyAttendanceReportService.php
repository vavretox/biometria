<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\WorkScheduleDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DailyAttendanceReportService
{
    public const Fields = [
        'unit' => 'Unidad', 'position' => 'Cargo', 'employee_code' => 'Código de empleado',
        'identity_document' => 'Documento', 'item_number' => 'Ítem', 'department' => 'Departamento',
        'office' => 'Oficina', 'employee_type' => 'Tipo de empleado', 'biometric_id' => 'ID biométrico',
    ];

    public const Columns = [
        'schedule' => 'Horario asignado', 'source' => 'Origen y biométrico',
        'observation' => 'Observaciones', 'late_minutes' => 'Minutos de retraso registrados',
    ];

    public const DefaultFields = ['unit', 'position', 'employee_code', 'identity_document'];

    public const DefaultColumns = ['schedule', 'source', 'observation'];

    public function __construct(
        private readonly AttendanceSummaryService $summaryService,
        private readonly PayrollPenaltyCalculator $penalties,
    ) {}

    /** @param array<string, mixed> $filters @return Collection<int, Employee> */
    public function employees(array $filters, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $overlaps = fn ($query) => $query->whereDate('starts_on', '<=', $to->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()));
        $scheduleType = $filters['schedule_type'] ?? null;
        $matchesSchedule = fn ($query) => $scheduleType === 'split'
            ? $query->whereHas('days', fn ($query) => $query->whereNotNull('second_starts_at'))
            : $query->whereDoesntHave('days', fn ($query) => $query->whereNotNull('second_starts_at'));

        return Employee::query()->with([
            'workUnit:id,name', 'office:id,name', 'workSchedule.days',
            'attendances' => fn ($query) => $query->whereBetween('checked_at', [$from, $to])
                ->when($filters['device_id'] ?? null, fn ($query, $id) => $query->where('device_id', $id))
                ->with('device:id,device_name')->orderBy('checked_at')->orderBy('id'),
            'scheduleAssignments' => fn ($query) => $overlaps($query)->with('workSchedule.days')->oldest('starts_on'),
            'leaveRequests' => fn ($query) => $query->where('status', 'approved')
                ->whereDate('starts_on', '<=', $to->toDateString())->whereDate('ends_on', '>=', $from->toDateString())
                ->with('leaveType:id,name,abbreviation,color')->orderBy('id'),
        ])
            ->when($filters['work_unit_id'] ?? null, fn ($query, $id) => $query->where('work_unit_id', $id))
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['device_id'] ?? null, fn ($query, $id) => $query->where(fn ($query) => $query
                ->whereHas('attendances', fn ($query) => $query->where('device_id', $id))
                ->orWhereHas('biometricTemplates', fn ($query) => $query->where('device_id', $id))))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $query->where(function (Builder $query) use ($word): void {
                        foreach (['first_name', 'last_name', 'maternal_last_name', 'employee_code', 'identity_document'] as $index => $field) {
                            if ($index === 0) {
                                $query->where($field, 'like', '%'.$word.'%');
                            } else {
                                $query->orWhere($field, 'like', '%'.$word.'%');
                            }
                        }
                    });
                }
            })
            ->when($scheduleType, fn ($query) => $query->where(fn ($query) => $query
                ->whereHas('scheduleAssignments', fn ($query) => $overlaps($query)->whereHas('workSchedule', $matchesSchedule))
                ->orWhere(fn ($query) => $query->whereDoesntHave('scheduleAssignments', $overlaps)->whereHas('workSchedule', $matchesSchedule))))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     * @return array{employee: Employee, days: Collection, has_split: bool, has_additional: bool, mark_count: int, covered_count: int, holiday_count: int, late_minutes: int, absence_days: int, calendar_days: int, absence_discount_days: float, abandonment_days: float, abandonment_discount_days: float, unpaid_leave_days: float, unpaid_leave_discount_days: float}
     */
    public function build(Employee $employee, CarbonInterface $from, CarbonInterface $to, Collection $holidays, ?string $scheduleType = null): array
    {
        $summary = $this->summaryService->summarize($employee, Carbon::parse($from->toDateString()), Carbon::parse($to->toDateString()));
        $days = $summary['days']->filter(function (array $day) use ($scheduleType): bool {
            if ($scheduleType === null) {
                return true;
            }
            $isSplit = $day['schedule']?->days->contains(fn ($scheduleDay): bool => $scheduleDay->second_starts_at !== null) ?? false;

            return $day['schedule'] !== null && ($scheduleType === 'split' ? $isSplit : ! $isSplit);
        })->map(function (array $day) use ($holidays): array {
            $date = $day['date'];
            $dayHolidays = $holidays->filter(fn (Holiday $holiday): bool => $holiday->date->isSameDay($date)
                || ($holiday->is_recurring && $holiday->date->format('m-d') === $date->format('m-d')))->toBase();
            $scheduleDay = $day['schedule']?->days->firstWhere('day_of_week', $date->isoWeekday());
            $isSplit = $scheduleDay !== null && $scheduleDay->second_starts_at !== null;
            $isWorkingDay = (bool) $scheduleDay?->is_working_day;
            $cells = [];
            for ($index = 0; $index < 4; $index++) {
                $slotIndex = $isSplit ? $index : match ($index) {
                    0 => 0, 3 => 1, default => null
                };
                $slot = $slotIndex !== null ? $day['slots']->get($slotIndex) : null;
                $mark = $slot['mark'] ?? null;
                $leave = $slot['leave'] ?? null;
                $code = $leave ? $this->leaveCode($leave) : null;
                $value = $mark?->checked_at->format('H:i') ?? ($code ?? ($slot && $dayHolidays->isNotEmpty() ? 'FE' : ($slot && $isWorkingDay ? 'SIN MARCADO' : '—')));
                if ($mark && $code) {
                    $value .= ' · '.$code;
                }
                $cells[] = [
                    'value' => $value,
                    'color' => $leave?->leaveType?->color ?? ($slot && $dayHolidays->isNotEmpty() && ! $mark ? '#BE123C' : null),
                    'description' => $leave?->leaveType?->name ?? ($slot && $dayHolidays->isNotEmpty() ? $dayHolidays->pluck('name')->implode(', ') : null),
                    'expected' => $slot['expected_time'] ?? null,
                ];
            }
            $marks = $day['slots']->pluck('mark')->filter();
            $additional = $day['slots']->slice($isSplit ? 4 : 2)->filter(fn (array $slot): bool => $slot['mark'] !== null)
                ->map(fn (array $slot): string => $slot['label'].': '.$slot['mark']->checked_at->format('H:i'));
            $notes = $marks->pluck('observation')->filter()->unique()->implode(' · ');
            $incidences = $dayHolidays->map(fn (Holiday $holiday): string => 'FE · '.$holiday->name)
                ->merge($day['leaves']->where('status', 'approved')->map(fn (LeaveRequest $leave): string => $this->leaveCode($leave).' · '.($leave->leaveType?->name ?? $leave->type)
                    .' ('.match ($leave->duration_type) {
                        'half_day' => 'medio día, '.($leave->half_day_period === 'morning' ? 'mañana' : 'tarde'), 'hours' => 'por horas', default => 'día completo'
                    }.')'))->implode(' · ');
            $sources = $marks->map(fn ($mark): string => match ($mark->source) {
                'biometric' => 'Biometría'.($mark->device ? ' · '.$mark->device->device_name : ''),
                'manual' => 'Manual', 'automatic' => 'Automática', 'access_code' => 'Código', default => ucfirst($mark->source ?? ''),
            })->unique()->implode(' / ');
            $status = match (true) {
                $dayHolidays->isNotEmpty() => 'Feriado',
                $day['schedule'] === null => 'Sin horario',
                ! $isWorkingDay && $marks->isEmpty() => $day['leaves']->where('status', 'approved')->isNotEmpty() ? 'Con permiso' : 'No laboral',
                $day['expectedCount'] > 0 && $day['completedCount'] >= $day['expectedCount'] => $day['coveredCount'] ? 'Completo con permiso' : 'Completo',
                $marks->isNotEmpty() || $day['coveredCount'] > 0 => 'Incompleto',
                default => 'SIN MARCADO',
            };

            $isAbsence = $isWorkingDay && $day['expectedCount'] > 0 && $dayHolidays->isEmpty()
                && $marks->isEmpty() && $day['coveredCount'] === 0;
            $missingCount = $day['slots']->filter(fn (array $slot): bool => $slot['expected_time'] !== null
                && $slot['mark'] === null && $slot['leave'] === null)->count();
            $abandonmentDays = $isWorkingDay && $dayHolidays->isEmpty() && ! $isAbsence ? $missingCount * 0.5 : 0.0;
            $unpaidLeaveDays = $isWorkingDay && $dayHolidays->isEmpty()
                ? $this->unpaidLeaveDays($day['leaves'], $scheduleDay, $date) : 0.0;

            return [
                'date' => $date, 'weekday' => ucfirst($date->locale('es')->translatedFormat('l')),
                'schedule' => $day['schedule']?->name ?? 'Sin horario', 'is_split' => $isSplit,
                'cells' => $cells, 'incidences' => $incidences, 'status' => $status,
                'source' => $sources, 'observation' => $notes, 'additional_marks' => $additional->implode(' · '), 'mark_count' => $marks->count(),
                'covered_count' => $day['coveredCount'], 'is_holiday' => $dayHolidays->isNotEmpty(),
                'is_absence' => $isAbsence,
                'abandonment_days' => $abandonmentDays, 'unpaid_leave_days' => $unpaidLeaveDays,
                'late_minutes' => (int) $marks->sum('late_minutes'),
            ];
        })->values();

        $absenceDays = $days->where('is_absence', true)->count();
        $abandonmentDays = (float) $days->sum('abandonment_days');
        $unpaidLeaveDays = (float) $days->sum('unpaid_leave_days');

        return [
            'employee' => $employee, 'days' => $days, 'has_split' => $days->contains('is_split', true),
            'has_additional' => $days->contains(fn (array $day): bool => $day['additional_marks'] !== ''),
            'mark_count' => $days->sum('mark_count'), 'covered_count' => $days->sum('covered_count'),
            'holiday_count' => $days->where('is_holiday', true)->count(), 'late_minutes' => $days->sum('late_minutes'),
            'calendar_days' => $summary['days']->count(),
            'absence_days' => $absenceDays,
            'absence_discount_days' => $this->penalties->absenceDays($absenceDays),
            'abandonment_days' => $abandonmentDays,
            'abandonment_discount_days' => $this->penalties->abandonmentDays($abandonmentDays),
            'unpaid_leave_days' => $unpaidLeaveDays,
            'unpaid_leave_discount_days' => $this->penalties->unpaidLeaveDays($unpaidLeaveDays),
        ];
    }

    /** @param list<string> $fields @return array<string, string> */
    public function metadata(Employee $employee, array $fields): array
    {
        $values = [
            'unit' => $employee->workUnit?->name, 'position' => $employee->position,
            'employee_code' => $employee->employee_code, 'identity_document' => $employee->identity_document,
            'item_number' => $employee->item_number, 'department' => $employee->department,
            'office' => $employee->office?->name, 'employee_type' => match ($employee->employee_type) {
                'permanent' => 'Permanente', 'contract' => 'Contrato', 'consultant' => 'Consultor', 'temporary' => 'Temporal', default => $employee->employee_type,
            }, 'biometric_id' => $employee->biometric_id,
        ];
        $metadata = [];
        foreach ($fields as $field) {
            if (isset(self::Fields[$field])) {
                $metadata[self::Fields[$field]] = (string) ($values[$field] ?? 'Sin registrar');
            }
        }

        return $metadata;
    }

    /** @param Collection<int, LeaveRequest> $leaves */
    private function unpaidLeaveDays(Collection $leaves, WorkScheduleDay $scheduleDay, CarbonInterface $date): float
    {
        $leaves = $leaves->filter(fn (LeaveRequest $leave): bool => $leave->status === 'approved'
            && mb_strtoupper(trim($leave->leaveType?->abbreviation ?? '')) === 'SGH');
        $shifts = collect([[$scheduleDay->starts_at, $scheduleDay->ends_at], [$scheduleDay->second_starts_at, $scheduleDay->second_ends_at]])
            ->filter(fn (array $shift): bool => $shift[0] !== null && $shift[1] !== null)
            ->map(fn (array $shift): array => [Carbon::parse($date->toDateString().' '.$shift[0]), Carbon::parse($date->toDateString().' '.$shift[1])]);
        $scheduledMinutes = $shifts->sum(fn (array $shift): float => max(0, $shift[0]->diffInMinutes($shift[1], false)));
        if ($scheduledMinutes <= 0 || $leaves->isEmpty()) {
            return 0.0;
        }
        $unpaidMinutes = 0.0;
        foreach ($shifts->values() as $index => $shift) {
            $intervals = $leaves->map(function (LeaveRequest $leave) use ($shift, $index, $shifts): ?array {
                if ($leave->duration_type === 'all_day') {
                    return $shift;
                }
                if ($leave->duration_type === 'half_day') {
                    if ($shifts->count() === 2) {
                        return ($leave->half_day_period === 'morning' ? $index === 0 : $index === 1) ? $shift : null;
                    }
                    $midpoint = $shift[0]->copy()->addMinutes($shift[0]->diffInMinutes($shift[1]) / 2);

                    return $leave->half_day_period === 'morning' ? [$shift[0], $midpoint] : [$midpoint, $shift[1]];
                }
                if ($leave->duration_type === 'hours' && $leave->starts_at && $leave->ends_at) {
                    return [$leave->starts_on->copy()->setTimeFromTimeString($leave->starts_at), $leave->ends_on->copy()->setTimeFromTimeString($leave->ends_at)];
                }

                return null;
            })->filter()->map(fn (array $interval): array => [max($shift[0]->getTimestamp(), $interval[0]->getTimestamp()), min($shift[1]->getTimestamp(), $interval[1]->getTimestamp())])
                ->filter(fn (array $interval): bool => $interval[1] > $interval[0])->sortBy(fn (array $interval): int => $interval[0]);
            $coveredUntil = $shift[0]->getTimestamp();
            foreach ($intervals as [$start, $end]) {
                $unpaidMinutes += max(0, $end - max($start, $coveredUntil)) / 60;
                $coveredUntil = max($coveredUntil, $end);
            }
        }

        return round($unpaidMinutes / $scheduledMinutes, 4);
    }

    private function leaveCode(LeaveRequest $leave): string
    {
        return $leave->leaveType?->abbreviation ?: mb_strtoupper(mb_substr($leave->type, 0, 3));
    }
}

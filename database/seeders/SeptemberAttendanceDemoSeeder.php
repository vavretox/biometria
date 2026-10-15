<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AttendanceSummaryService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeptemberAttendanceDemoSeeder extends Seeder
{
    public const Marker = 'PRUEBA-SEPTIEMBRE-2026';

    public function run(): void
    {
        DB::transaction(function (): void {
            $employees = Employee::with(['workSchedule.days', 'scheduleAssignments.workSchedule.days'])->orderBy('id')->get();
            $types = LeaveType::where('is_active', true)->orderBy('id')->get();
            $unpaid = $types->first(fn (LeaveType $type): bool => mb_strtoupper($type->abbreviation ?? '') === 'SGH');
            if ($employees->count() !== 3 || $unpaid === null) {
                throw new RuntimeException('Esta prueba requiere los tres empleados existentes y el permiso SGH activo.');
            }
            $holiday = Holiday::firstOrCreate(['date' => '2026-09-24'], [
                'name' => self::Marker.' · Feriado de prueba', 'scope' => 'institutional', 'is_recurring' => false,
            ]);
            $holidays = Holiday::get();
            foreach ($employees->values() as $employeeIndex => $employee) {
                $originalDates = Attendance::where('employee_id', $employee->id)
                    ->whereBetween('checked_at', ['2026-09-01 00:00:00', '2026-09-30 23:59:59'])
                    ->where(fn ($query) => $query->whereNull('observation')->orWhere('observation', 'not like', self::Marker.'%'))
                    ->get()->map(fn (Attendance $mark): string => $mark->checked_at->toDateString());
                $originalLeaves = LeaveRequest::where('employee_id', $employee->id)
                    ->whereDate('starts_on', '<=', '2026-09-30')->whereDate('ends_on', '>=', '2026-09-01')
                    ->where(fn ($query) => $query->whereNull('reason')->orWhere('reason', 'not like', self::Marker.'%'))->get();
                $scenarios = $this->scenarios($types, $employeeIndex);
                $scenarioIndex = 0;
                foreach (CarbonPeriod::create('2026-09-01', '2026-09-30') as $date) {
                    $scheduleDay = $employee->attendanceScheduleForDate($date)?->days->firstWhere('day_of_week', $date->isoWeekday());
                    if (! $scheduleDay?->is_working_day || $originalDates->contains($date->toDateString())
                        || $originalLeaves->contains(fn (LeaveRequest $leave): bool => $date->betweenIncluded($leave->starts_on, $leave->ends_on))
                        || $holidays->contains(fn (Holiday $day): bool => $day->date->isSameDay($date) || ($day->is_recurring && $day->date->format('m-d') === $date->format('m-d')))) {
                        continue;
                    }
                    $scenario = $scenarios[$scenarioIndex++] ?? ['label' => 'Jornada completa', 'omissions' => 0, 'late_minutes' => 0, 'leave' => null];
                    $reason = self::Marker.' · '.$date->toDateString().' · '.$scenario['label'];
                    $leaves = collect();
                    if ($scenario['leave'] !== null) {
                        $specification = $scenario['leave'];
                        $startsAt = Carbon::parse($date->toDateString().' '.$scheduleDay->starts_at);
                        $leave = LeaveRequest::firstOrCreate(['employee_id' => $employee->id, 'reason' => $reason], [
                            'leave_type_id' => $specification['type']->id, 'type' => $specification['type']->code,
                            'starts_on' => $date->toDateString(), 'ends_on' => $date->toDateString(),
                            'duration_type' => $specification['duration'], 'half_day_period' => $specification['period'],
                            'starts_at' => $specification['duration'] === 'hours' ? $startsAt->format('H:i') : null,
                            'ends_at' => $specification['duration'] === 'hours' ? $startsAt->copy()->addHours(2)->format('H:i') : null,
                            'status' => $specification['status'], 'approved_at' => $specification['status'] === 'approved' ? now() : null,
                        ]);
                        $leaves->push($leave);
                    }
                    $fixture = clone $employee;
                    $fixture->setRelation('leaveRequests', $leaves);
                    $fixture->setRelation('attendances', collect());
                    $day = app(AttendanceSummaryService::class)->summarize($fixture, $date, $date)['days']->first();
                    $slots = $day['slots']->filter(fn (array $slot): bool => $slot['expected_time'] !== null && $slot['leave'] === null)->values();
                    $omissions = $scenario['omissions'] === -1 ? $slots->count() : $scenario['omissions'];
                    foreach ($slots->take(max(0, $slots->count() - $omissions)) as $index => $slot) {
                        $minutes = $index === 0 && $slot['type'] === 'in' ? $scenario['late_minutes'] : 0;
                        $checkedAt = Carbon::parse($date->toDateString().' '.$slot['expected_time'])->addMinutes($minutes);
                        Attendance::firstOrCreate(['employee_id' => $employee->id, 'checked_at' => $checkedAt], [
                            'type' => $slot['type'], 'status' => $minutes > 0 ? 'late' : 'normal', 'late_minutes' => $minutes,
                            'source' => 'manual', 'device_id' => null, 'observation' => $reason,
                            'raw_data' => ['demo' => self::Marker, 'scenario' => $scenario['label']],
                        ]);
                    }
                    $this->command?->line($employee->id.' · '.$date->toDateString().' · '.$scenario['label']);
                }
                if ($scenarioIndex < count($scenarios)) {
                    throw new RuntimeException('No hay suficientes días libres para todos los casos del empleado '.$employee->id.'.');
                }
            }
            $this->command?->info('Prueba de septiembre creada. Los registros originales se conservaron.');
        });
    }

    /**
     * @param  Collection<int, LeaveType>  $types
     * @return list<array{label: string, omissions: int, late_minutes: int, leave: null|array{type: LeaveType, duration: string, period: ?string, status: string}}>
     */
    public function scenarios(Collection $types, int $employeeIndex): array
    {
        $unpaid = $types->first(fn (LeaveType $type): bool => mb_strtoupper($type->abbreviation ?? '') === 'SGH');
        if ($unpaid === null) {
            throw new RuntimeException('Falta el permiso SGH.');
        }
        $scenarios = [
            ['label' => 'Jornada completa', 'omissions' => 0, 'late_minutes' => 0, 'leave' => null],
            ['label' => 'Atraso acumulable', 'omissions' => 0, 'late_minutes' => [30, 60, 125][$employeeIndex], 'leave' => null],
            ['label' => 'Inasistencia de día completo', 'omissions' => -1, 'late_minutes' => 0, 'leave' => null],
            ['label' => 'Abandono por una omisión', 'omissions' => 1, 'late_minutes' => 0, 'leave' => null],
            ['label' => 'Abandono por dos omisiones', 'omissions' => 2, 'late_minutes' => 0, 'leave' => null],
            ['label' => 'Abandono por tres omisiones', 'omissions' => 3, 'late_minutes' => 0, 'leave' => null],
        ];
        foreach ([['all_day', null], ['half_day', 'morning'], ['half_day', 'afternoon'], ['hours', null]] as [$duration, $period]) {
            $scenarios[] = ['label' => 'SGH '.$duration.' '.($period ?? ''), 'omissions' => 0, 'late_minutes' => 0,
                'leave' => ['type' => $unpaid, 'duration' => $duration, 'period' => $period, 'status' => 'approved']];
        }
        foreach (['pending', 'rejected'] as $status) {
            $scenarios[] = ['label' => 'Permiso '.$status.' sin cobertura', 'omissions' => -1, 'late_minutes' => 0,
                'leave' => ['type' => $unpaid, 'duration' => 'all_day', 'period' => null, 'status' => $status]];
        }
        foreach ($types->reject(fn (LeaveType $type): bool => $type->id === $unpaid->id)->values() as $index => $type) {
            if ($index % 3 !== $employeeIndex) {
                continue;
            }
            $duration = ['all_day', 'half_day', 'hours'][$index % 3];
            $scenarios[] = ['label' => 'Permiso '.$type->abbreviation.' '.$duration, 'omissions' => 0, 'late_minutes' => 0,
                'leave' => ['type' => $type, 'duration' => $duration, 'period' => $duration === 'half_day' ? 'morning' : null, 'status' => 'approved']];
        }

        return $scenarios;
    }
}

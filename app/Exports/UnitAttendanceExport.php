<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\WorkUnit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class UnitAttendanceExport implements FromArray, WithEvents, WithTitle
{
    /** @var list<int> */
    private array $employeeHeaderRows = [];

    /** @var list<int> */
    private array $tableHeaderRows = [];

    /** @var list<array{cell: string, color: string}> */
    private array $coloredCells = [];

    /** @var Collection<int, CarbonInterface> */
    private Collection $dates;

    /** @var Collection<int, Employee>|null */
    private ?Collection $employeeCollection = null;

    public function __construct(
        private readonly WorkUnit $workUnit,
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
        private readonly ?int $employeeId = null,
        private readonly ?string $scheduleType = null,
    ) {
        $this->dates = collect(CarbonPeriod::create($this->from->startOfDay(), $this->to->startOfDay()));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [
            ['REPORTE DETALLADO DE ASISTENCIA POR UNIDAD'],
            ['Unidad', $this->workUnit->name, 'Período', $this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y')],
            ['Generado', now()->format('d/m/Y H:i')],
            [],
        ];
        $holidays = Holiday::orderBy('date')->get();

        foreach ($this->employees() as $employee) {
            $dailyData = $this->dailyData($employee, $holidays);
            $this->employeeHeaderRows[] = count($rows) + 1;
            $rows[] = [mb_strtoupper($employee->full_name).' ('.($employee->identity_document ?: 'SIN DOCUMENTO').')'];
            $this->tableHeaderRows[] = count($rows) + 1;
            $rows[] = ['HORARIO', ...$dailyData->pluck('schedule')->all()];
            $rows[] = ['MES', ...$this->dates->map(fn (CarbonInterface $date): string => mb_strtoupper($date->translatedFormat('M')))->all()];
            $rows[] = ['DÍA', ...$this->dates->map(fn (CarbonInterface $date): int => $date->day)->all()];
            $rows[] = ['SEMANA', ...$this->dates->map(fn (CarbonInterface $date): string => mb_strtoupper(mb_substr($date->translatedFormat('D'), 0, 2)))->all()];
            $rows[] = ['MAÑANA ENTRADA', ...$dailyData->pluck('morning_in')->all()];
            $rows[] = ['MAÑANA SALIDA', ...$dailyData->pluck('morning_out')->all()];
            $rows[] = ['TARDE ENTRADA', ...$dailyData->pluck('afternoon_in')->all()];
            $rows[] = ['TARDE SALIDA', ...$dailyData->pluck('afternoon_out')->all()];
            $rows[] = ['RETRASOS', ...$dailyData->pluck('late_minutes')->all()];
            $rows[] = ['RET. C/TOL.', ...$dailyData->pluck('late_after_grace')->all()];
            $rows[] = ['INCIDENCIA', ...$dailyData->pluck('incidence')->all()];
            $incidenceRow = count($rows);
            $dailyData->each(function (array $day, int $index) use ($incidenceRow): void {
                if ($day['leave_color'] !== null) {
                    $this->coloredCells[] = [
                        'cell' => Coordinate::stringFromColumnIndex($index + 2).$incidenceRow,
                        'color' => $day['leave_color'],
                    ];
                }
            });
            $rows[] = [];
            $rows[] = ['MINUTOS RETRASO', $dailyData->sum(fn (array $day): int => (int) $day['late_after_grace'])];
            $rows[] = ['DÍAS FALTA', $dailyData->where('is_absent', true)->count()];
            $rows[] = ['DÍAS INCOMPLETOS', $dailyData->where('is_incomplete', true)->count()];
            $rows[] = [];
        }

        if ($this->employees()->isEmpty()) {
            $rows[] = ['No existen empleados para los filtros seleccionados.'];
        }

        $leaveTypes = $this->employees()->flatMap->leaveRequests->pluck('leaveType')->filter()->unique('id')->sortBy('name')->values();
        $rows[] = ['CÓDIGOS', 'FE = Feriado', ...$leaveTypes->map(fn ($leaveType): string => $leaveType->abbreviation.' = '.$leaveType->name), '- = No laboral'];
        $legendRow = count($rows);
        $leaveTypes->each(function ($leaveType, int $index) use ($legendRow): void {
            $this->coloredCells[] = [
                'cell' => Coordinate::stringFromColumnIndex($index + 3).$legendRow,
                'color' => $leaveType->color,
            ];
        });

        return $rows;
    }

    public function title(): string
    {
        return 'Detalle de asistencia';
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex($this->dates->count() + 1);
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('A1:'.$lastColumn.'1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
                $sheet->getStyle('A1:'.$lastColumn.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach ($this->employeeHeaderRows as $row) {
                    $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
                    $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
                    $sheet->getStyle('A'.$row)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                }

                foreach ($this->tableHeaderRows as $row) {
                    $sheet->getStyle('A'.$row.':'.$lastColumn.($row + 3))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');
                    $sheet->getStyle('A'.$row.':'.$lastColumn.($row + 3))->getFont()->setBold(true);
                }

                foreach ($this->dates as $index => $date) {
                    $column = Coordinate::stringFromColumnIndex($index + 2);
                    $sheet->getColumnDimension($column)->setWidth(11);

                    if ($date->isWeekend()) {
                        $sheet->getStyle($column.'2:'.$column.$highestRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
                    }
                }

                foreach ($this->coloredCells as $coloredCell) {
                    $argb = 'FF'.mb_strtoupper(ltrim($coloredCell['color'], '#'));
                    $sheet->getStyle($coloredCell['cell'])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
                    $sheet->getStyle($coloredCell['cell'])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                }

                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getStyle('A1:'.$lastColumn.$highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');
                $sheet->getStyle('B2:'.$lastColumn.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:'.$lastColumn.$highestRow)->getAlignment()->setWrapText(true);
                $sheet->freezePane('B5');
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->getPageMargins()->setTop(0.3)->setRight(0.3)->setBottom(0.3)->setLeft(0.3);
            },
        ];
    }

    /** @return Collection<int, Employee> */
    private function employees(): Collection
    {
        return $this->employeeCollection ??= Employee::query()
            ->where('work_unit_id', $this->workUnit->id)
            ->when($this->employeeId, fn ($query, $employeeId) => $query->whereKey($employeeId))
            ->when($this->scheduleType, fn ($query, $type) => $query->whereHas(
                'scheduleAssignments',
                fn ($query) => $this->overlappingAssignments($query)->whereHas(
                    'workSchedule',
                    fn ($query) => $type === 'split'
                        ? $query->whereHas('days', fn ($query) => $query->whereNotNull('second_starts_at'))
                        : $query->whereDoesntHave('days', fn ($query) => $query->whereNotNull('second_starts_at')),
                ),
            ))
            ->with([
                'attendances' => fn ($query) => $query->whereBetween('checked_at', [$this->from, $this->to])->oldest('checked_at'),
                'scheduleAssignments' => fn ($query) => $this->overlappingAssignments($query)->with('workSchedule.days')->oldest('starts_on'),
                'leaveRequests' => fn ($query) => $query
                    ->where('status', 'approved')
                    ->whereDate('starts_on', '<=', $this->to)
                    ->whereDate('ends_on', '>=', $this->from)
                    ->with('leaveType:id,name,abbreviation,color'),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function overlappingAssignments(Builder|HasMany $query): Builder|HasMany
    {
        return $query
            ->whereDate('starts_on', '<=', $this->to->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $this->from->toDateString()));
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     * @return Collection<int, array<string, mixed>>
     */
    private function dailyData(Employee $employee, Collection $holidays): Collection
    {
        $attendancesByDate = $employee->attendances->groupBy(fn (Attendance $attendance): string => $attendance->checked_at->toDateString());

        return $this->dates->map(function (CarbonInterface $date) use ($attendancesByDate, $employee, $holidays): array {
            $schedule = $employee->scheduleForDate($date);
            $scheduleDay = $schedule?->days->firstWhere('day_of_week', $date->dayOfWeekIso);
            $holiday = $holidays->first(fn (Holiday $holiday): bool => $holiday->date->isSameDay($date)
                || ($holiday->is_recurring && $holiday->date->format('m-d') === $date->format('m-d')));
            $leave = $employee->leaveRequests->first(fn (LeaveRequest $leave): bool => $leave->starts_on->lte($date) && $leave->ends_on->gte($date));
            $attendances = $attendancesByDate->get($date->toDateString(), collect())->values();
            $isWorkingDay = $scheduleDay?->is_working_day ?? false;
            $isSplit = $scheduleDay?->second_starts_at !== null;

            if ($this->scheduleType !== null && ($this->scheduleType === 'split') !== $isSplit) {
                $schedule = null;
                $scheduleDay = null;
                $isWorkingDay = false;
                $isSplit = false;
            }

            $incidence = $holiday ? 'FE' : ($leave ? $this->leaveCode($leave) : ($isWorkingDay ? '' : '-'));
            $lateMinutes = $attendances->sum('late_minutes');
            $graceMinutes = $scheduleDay?->grace_minutes ?? 0;
            $expectedMarks = $isSplit ? 4 : 2;

            return [
                'schedule' => $schedule ? ($isSplit ? 'DISC' : 'CONT') : 'SIN HOR.',
                'morning_in' => $this->markValue($attendances, 0, $incidence),
                'morning_out' => $this->markValue($attendances, $isSplit ? 1 : ($attendances->count() >= 2 ? $attendances->count() - 1 : -1), $incidence),
                'afternoon_in' => $isSplit ? $this->markValue($attendances, 2, $incidence) : ($incidence ?: '-'),
                'afternoon_out' => $isSplit ? $this->markValue($attendances, 3, $incidence) : ($incidence ?: '-'),
                'late_minutes' => $lateMinutes ?: '',
                'late_after_grace' => $lateMinutes > $graceMinutes ? $lateMinutes - $graceMinutes : '',
                'incidence' => $incidence,
                'leave_color' => $leave?->leaveType?->color,
                'is_absent' => $isWorkingDay && ! $holiday && ! $leave && $attendances->isEmpty(),
                'is_incomplete' => $isWorkingDay && ! $holiday && ! $leave && $attendances->isNotEmpty() && $attendances->count() < $expectedMarks,
            ];
        });
    }

    private function markValue(Collection $attendances, int $index, string $incidence): string
    {
        if ($index < 0) {
            return $incidence;
        }

        return $attendances->get($index)?->checked_at->format('H:i') ?? $incidence;
    }

    private function leaveCode(LeaveRequest $leave): string
    {
        if ($leave->leaveType?->abbreviation) {
            return $leave->leaveType->abbreviation;
        }

        return match ($leave->type) {
            'vacation' => 'VL',
            'medical' => 'EF',
            'commission' => 'CO',
            default => 'E4',
        };
    }
}

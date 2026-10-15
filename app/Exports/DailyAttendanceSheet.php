<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class DailyAttendanceSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    private int $headerRow = 1;

    private int $lastDataRow = 1;

    private int $columnCount = 1;

    /** @var list<array{cell: string, color: string, description: string}> */
    private array $coloredCells = [];

    /** @var list<int> */
    private array $holidayRows = [];

    /** @param array<string, mixed> $report @param list<string> $columns */
    public function __construct(
        private readonly array $report,
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $to,
        private readonly array $columns,
    ) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $this->coloredCells = [];
        $this->holidayRows = [];
        $rows = [
            ['REPORTE DIARIO DE ASISTENCIA'],
            ['Período', $this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y')],
            ['Empleado', $this->report['employee']->full_name],
        ];
        foreach ($this->report['metadata'] as $label => $value) {
            $rows[] = [$label, $value];
        }
        if (in_array('late_minutes', $this->columns, true)) {
            $rows[] = ['Nota', 'Retraso: suma de minutos guardados en las marcaciones. Fórmula adicional pendiente.'];
        }
        $rows[] = [''];
        $this->headerRow = count($rows) + 1;
        $headings = ['Fecha', 'Día'];
        if (in_array('schedule', $this->columns, true)) {
            $headings[] = 'Horario';
        }
        $slotIndexes = $this->report['has_split'] ? [0, 1, 2, 3] : [0, 3];
        $headings = [...$headings, ...($this->report['has_split'] ? ['Entrada', 'Salida mediodía', 'Retorno', 'Salida'] : ['Entrada', 'Salida']), 'Estado', 'Permisos y feriados'];
        foreach (['source' => 'Origen / biométrico', 'observation' => 'Observaciones', 'late_minutes' => 'Retraso registrado (min)'] as $column => $label) {
            if (in_array($column, $this->columns, true)) {
                $headings[] = $label;
            }
        }
        if ($this->report['has_additional']) {
            $headings[] = 'Otras marcaciones';
        }
        $this->columnCount = count($headings);
        $rows[] = $headings;
        foreach ($this->report['days'] as $day) {
            $row = [$day['date']->toDateString(), $day['weekday']];
            if (in_array('schedule', $this->columns, true)) {
                $row[] = $day['schedule'];
            }
            $rowNumber = count($rows) + 1;
            if ($day['is_holiday']) {
                $this->holidayRows[] = $rowNumber;
            }
            foreach ($slotIndexes as $index) {
                $cell = $day['cells'][$index];
                $row[] = $cell['value'];
                if ($cell['color']) {
                    $this->coloredCells[] = [
                        'cell' => Coordinate::stringFromColumnIndex(count($row)).$rowNumber,
                        'color' => $cell['color'],
                        'description' => ($cell['description'] ?? '').($cell['expected'] ? ' · Hora esperada: '.$cell['expected'] : ''),
                    ];
                }
            }
            $row[] = $day['status'];
            $row[] = $day['incidences'];
            foreach (['source', 'observation', 'late_minutes'] as $column) {
                if (in_array($column, $this->columns, true)) {
                    $row[] = $day[$column];
                }
            }
            if ($this->report['has_additional']) {
                $row[] = $day['additional_marks'];
            }
            $rows[] = $row;
        }
        $this->lastDataRow = count($rows);
        $rows[] = [''];
        $rows[] = ['TOTALES'];
        $rows[] = ['Marcaciones reales', $this->report['mark_count']];
        $rows[] = ['Marcaciones cubiertas por permiso', $this->report['covered_count']];
        $rows[] = ['Días feriados', $this->report['holiday_count']];
        $rows[] = ['MINUTOS RETRASO', $this->report['late_minutes']];
        $rows[] = ['DÍAS FALTA', $this->report['absence_days']];
        $rows[] = ['DESCUENTO POR INASISTENCIA (DÍAS)', $this->report['absence_discount_days']];
        $rows[] = ['DÍAS ABANDONO', $this->report['abandonment_days']];
        $rows[] = ['DESCUENTO POR ABANDONO (DÍAS)', $this->report['abandonment_discount_days']];
        $rows[] = ['DÍAS LICENCIA SIN GOCE (SGH)', $this->report['unpaid_leave_days']];
        $rows[] = ['DESCUENTO POR LICENCIA SGH (DÍAS)', $this->report['unpaid_leave_discount_days']];
        $rows[] = [''];
        $rows[] = ['LEYENDA'];
        $rows[] = ['FE', 'Feriado'];
        $this->coloredCells[] = ['cell' => 'A'.count($rows), 'color' => '#BE123C', 'description' => 'Feriado'];
        foreach ($this->report['employee']->leaveRequests->where('status', 'approved')->pluck('leaveType')->filter()->unique('id') as $type) {
            $rows[] = [$type->abbreviation, $type->name];
            $this->coloredCells[] = ['cell' => 'A'.count($rows), 'color' => $type->color ?: '#2563EB', 'description' => $type->name];
        }
        $rows[] = ['SIN MARCADO', 'Marcación no registrada'];
        $rows[] = ['—', 'No corresponde a una marcación prevista'];

        return $rows;
    }

    public function title(): string
    {
        $employee = $this->report['employee'];
        $name = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $employee->last_name.' '.$employee->first_name);

        return $employee->id.' '.mb_substr(trim($name, " '"), 0, 30 - mb_strlen((string) $employee->id));
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = Coordinate::stringFromColumnIndex($this->columnCount);
            $lastRow = $sheet->getHighestRow();
            $sheet->mergeCells('A1:'.$lastColumn.'1');
            $sheet->getRowDimension(1)->setRowHeight(30);
            $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
            for ($row = 2; $row < $this->headerRow - 1; $row++) {
                $sheet->mergeCells('B'.$row.':'.$lastColumn.$row);
                $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            }
            $sheet->getStyle('A'.$this->headerRow.':'.$lastColumn.$this->headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
            $sheet->getStyle('A'.$this->headerRow.':'.$lastColumn.$this->headerRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getRowDimension($this->headerRow)->setRowHeight(32);
            $sheet->getStyle('A'.$this->headerRow.':'.$lastColumn.$this->lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');
            $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            for ($column = 1; $column <= $this->columnCount; $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column < 3 ? 19 : 22);
            }
            $sheet->getColumnDimension('A')->setWidth(34);
            foreach ($this->holidayRows as $row) {
                $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF1F2');
            }
            foreach ($this->coloredCells as $coloredCell) {
                $color = preg_match('/^#[0-9a-f]{6}$/i', $coloredCell['color']) ? substr($coloredCell['color'], 1) : '2563EB';
                $sheet->getStyle($coloredCell['cell'])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.strtoupper($color));
                $sheet->getStyle($coloredCell['cell'])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getComment($coloredCell['cell'])->getText()->createTextRun($coloredCell['description']);
            }
            if ($this->lastDataRow > $this->headerRow) {
                $sheet->setAutoFilter('A'.$this->headerRow.':'.$lastColumn.$this->lastDataRow);
            }
            $sheet->freezePane('C'.($this->headerRow + 1));
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0)->setPaperSize(PageSetup::PAPERSIZE_A4);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $this->headerRow);
            $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.3)->setRight(0.3);
        }];
    }
}

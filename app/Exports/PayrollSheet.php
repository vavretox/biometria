<?php

namespace App\Exports;

use App\Services\PayrollReportService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PayrollSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    /** @param Collection<int, array<int, mixed>> $rows @param array<string, string> $header */
    public function __construct(private readonly Collection $rows, private readonly CarbonInterface $from, private readonly CarbonInterface $to, private readonly array $header, private readonly string $sheetTitle = 'Planillas') {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return [
            ['RESUMEN PARA PLANILLAS'],
            ['Periodo', $this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y')],
            ['Lugar marcación', $this->header['payroll_location'] ?? ''],
            ['Dirección', $this->header['payroll_direction'] ?? ''],
            ['Teléfono', $this->header['payroll_phone'] ?? ''],
            ['Enc. asistencia', $this->header['payroll_contact'] ?? ''],
            ['Nota', 'Resumen del reporte de asistencia: inasistencia ×2, abandono ×1 y licencia SGH ×1. Atraso calculado por mes; 121+ requiere confirmar primera ocurrencia anual. Días trabajados = días calendario del periodo − días de inasistencia.'],
            [''], PayrollReportService::Headings, ...$this->rows->all(),
        ];
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
            $lastColumn = Coordinate::stringFromColumnIndex(count(PayrollReportService::Headings));
            $lastRow = $sheet->getHighestRow();
            $sheet->mergeCells('A1:'.$lastColumn.'1');
            foreach (range(2, 7) as $row) {
                $sheet->mergeCells('B'.$row.':'.$lastColumn.$row);
            }
            foreach (['A1:'.$lastColumn.'1', 'A9:'.$lastColumn.'9'] as $range) {
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
                $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            }
            $sheet->getRowDimension(9)->setRowHeight(65);
            $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setWrapText(true);
            $sheet->getStyle('A9:'.$lastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setWidth(20);
            }
            $sheet->freezePane('I10');
            $sheet->setAutoFilter('A9:'.$lastColumn.$lastRow);
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1, 9);
        }];
    }
}

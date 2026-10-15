<?php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class CombinedDailyAttendanceSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    /** @var list<array{start: int, header: int, columns: int, report: array<string, mixed>}> */
    private array $blocks = [];

    /** @param Collection<int, array<string, mixed>> $reports @param list<string> $columns */
    public function __construct(
        private readonly Collection $reports,
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $to,
        private readonly array $columns,
    ) {}

    public function title(): string
    {
        return 'Todos los empleados';
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $rows = [];
        $this->blocks = [];
        foreach ($this->reports as $report) {
            $block = (new DailyAttendanceSheet($report, $this->from, $this->to, $this->columns))->array();
            $header = array_search('Fecha', array_column($block, 0), true);
            $this->blocks[] = ['start' => count($rows) + 1, 'header' => count($rows) + $header + 1, 'columns' => count($block[$header]), 'report' => $report];
            $rows = [...$rows, ...$block, [''], ['']];
        }

        return $rows;
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            foreach ($this->blocks as $block) {
                $lastColumn = Coordinate::stringFromColumnIndex($block['columns']);
                $titleRange = 'A'.$block['start'].':'.$lastColumn.$block['start'];
                $sheet->mergeCells($titleRange);
                $sheet->getStyle($titleRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
                $sheet->getStyle($titleRange)->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
                $sheet->getRowDimension($block['start'])->setRowHeight(30);
                for ($row = $block['start'] + 1; $row < $block['header'] - 1; $row++) {
                    $sheet->mergeCells('B'.$row.':'.$lastColumn.$row);
                    $sheet->getStyle('A'.$row)->getFont()->setBold(true);
                }
                $headerRange = 'A'.$block['header'].':'.$lastColumn.$block['header'];
                $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $slotIndexes = $block['report']['has_split'] ? [0, 1, 2, 3] : [0, 3];
                $slotOffset = in_array('schedule', $this->columns, true) ? 4 : 3;
                foreach ($block['report']['days']->values() as $index => $day) {
                    $row = $block['header'] + $index + 1;
                    if ($day['is_holiday']) {
                        $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF1F2');
                    }
                    foreach ($slotIndexes as $offset => $slotIndex) {
                        $cell = $day['cells'][$slotIndex];
                        if ($cell['color']) {
                            $coordinate = Coordinate::stringFromColumnIndex($slotOffset + $offset).$row;
                            $color = preg_match('/^#[0-9a-f]{6}$/i', $cell['color']) ? substr($cell['color'], 1) : '2563EB';
                            $sheet->getStyle($coordinate)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.strtoupper($color));
                            $sheet->getStyle($coordinate)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                            $sheet->getComment($coordinate)->getText()->createTextRun($cell['description'] ?? '');
                        }
                    }
                }
            }
            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setWidth(22);
            }
            $sheet->getColumnDimension('A')->setWidth(34);
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true);
            $sheet->freezePane('C'.($this->blocks[0]['header'] + 1));
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0)->setPaperSize(PageSetup::PAPERSIZE_A4);
        }];
    }
}

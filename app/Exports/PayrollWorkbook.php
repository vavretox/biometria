<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollWorkbook implements Export, WithMultipleSheets
{
    /** @param Collection<int, array<int, mixed>> $rows @param array<string, string> $header */
    public function __construct(private readonly Collection $rows, private readonly CarbonInterface $from, private readonly CarbonInterface $to, private readonly array $header, private readonly bool $singleSheet = true) {}

    /** @return array<int, PayrollSheet> */
    public function sheets(): array
    {
        if ($this->singleSheet) {
            return [new PayrollSheet($this->rows, $this->from, $this->to, $this->header)];
        }

        return $this->rows->map(fn (array $row, int $index): PayrollSheet => new PayrollSheet(collect([$row]), $this->from, $this->to, $this->header, 'Empleado '.($index + 1)))->all();
    }
}

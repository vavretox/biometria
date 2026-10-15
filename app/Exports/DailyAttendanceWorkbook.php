<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyAttendanceWorkbook implements Export, WithMultipleSheets
{
    /** @param Collection<int, array<string, mixed>> $reports @param list<string> $columns */
    public function __construct(
        private readonly Collection $reports,
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $to,
        private readonly array $columns,
        private readonly bool $singleSheet = false,
    ) {}

    /** @return array<int, DailyAttendanceSheet|CombinedDailyAttendanceSheet> */
    public function sheets(): array
    {
        if ($this->singleSheet) {
            return [new CombinedDailyAttendanceSheet($this->reports, $this->from, $this->to, $this->columns)];
        }

        return $this->reports->map(fn (array $report): DailyAttendanceSheet => new DailyAttendanceSheet($report, $this->from, $this->to, $this->columns))->values()->all();
    }
}

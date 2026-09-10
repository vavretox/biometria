<?php

namespace App\Console\Commands;

use App\Services\AutomaticAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:generate-automatic {date?}')]
#[Description('Genera las marcaciones automáticas del personal ejecutivo')]
class GenerateAutomaticAttendances extends Command
{
    public function handle(AutomaticAttendanceService $service): int
    {
        $date = $this->argument('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->argument('date'))
            : today()->toImmutable();
        $result = $service->generate($date);

        $this->info(implode(' · ', [
            'Empleados: '.$result['employees'],
            'Marcas: '.$result['marks'],
            'Omitidos: '.$result['skipped'],
        ]));

        return self::SUCCESS;
    }
}

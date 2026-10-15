<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PayrollReportService
{
    public const Headings = ['Edificio', 'Partida', 'N°', 'Ítem', 'Cédula identidad', 'Apellido paterno', 'Apellido materno', 'Nombres', 'Días trabajados', '¿Marca?', 'Total minutos retraso', 'Faltas (días)', 'Abandonos (días)', 'Licencia sin goce de haber (días)', 'Total días a descontar', 'Descuento por atraso (días)', 'Descuento por inasistencia (días)', 'Descuento por abandono (días)', 'Descuento por licencia sin goce (días)'];

    public function __construct(private readonly PayrollPenaltyCalculator $penalties) {}

    /** @param Collection<int, array<string, mixed>> $reports @return Collection<int, array<int, mixed>> */
    public function rows(Collection $reports): Collection
    {
        return $reports->values()->map(function (array $report, int $index): array {
            $employee = $report['employee'];
            $datedDays = $report['days']->filter(fn (array $day): bool => isset($day['date'], $day['late_minutes']));
            $monthlyMinutes = $datedDays->isEmpty()
                ? collect([$report['late_minutes']])
                : $datedDays->groupBy(fn (array $day): string => $day['date']->format('Y-m'))
                    ->map(fn (Collection $days): int => (int) $days->sum('late_minutes'));
            $monthlyPenalties = $monthlyMinutes->map(fn (int $minutes): ?float => $this->penalties->latenessDays($minutes));
            $latenessDays = $monthlyPenalties->contains(fn (?float $penalty): bool => $penalty === null) ? null : (float) $monthlyPenalties->sum();
            $totalDiscountDays = $latenessDays === null ? 'Pendiente: 121+ minutos'
                : $latenessDays + $report['absence_discount_days'] + $report['abandonment_discount_days'] + $report['unpaid_leave_discount_days'];

            return [
                $employee->office?->name ?? 'Sin registrar', $employee->budget_line ?? '', $index + 1,
                $employee->item_number ?? '', $employee->identity_document ?? '',
                $employee->last_name, $employee->maternal_last_name ?? '', $employee->first_name,
                $report['calendar_days'] - $report['absence_days'], $report['mark_count'] > 0 ? 'S' : 'N',
                $report['late_minutes'], $report['absence_days'], $report['abandonment_days'], $report['unpaid_leave_days'], $totalDiscountDays,
                $latenessDays ?? 'Pendiente: 121+ minutos', $report['absence_discount_days'], $report['abandonment_discount_days'], $report['unpaid_leave_discount_days'],
            ];
        });
    }
}

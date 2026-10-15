<?php

namespace App\Services;

class PayrollPenaltyCalculator
{
    public function latenessDays(int $minutes, ?bool $firstOccurrenceInYear = null): ?float
    {
        return match (true) {
            $minutes <= 30 => 0.0,
            $minutes <= 45 => 0.5,
            $minutes <= 60 => 1.0,
            $minutes <= 90 => 2.0,
            $minutes <= 120 => 3.0,
            $firstOccurrenceInYear === true => 4.0,
            default => null,
        };
    }

    public function absenceDays(float $days): float
    {
        return $days * 2;
    }

    public function abandonmentDays(float $days): float
    {
        return $days;
    }

    public function unpaidLeaveDays(float $days): float
    {
        return $days;
    }

    public function totalDays(?float $lateness, ?float $absence, ?float $abandonment, ?float $unpaidLeave): ?float
    {
        if ($lateness === null || $absence === null || $abandonment === null || $unpaidLeave === null) {
            return null;
        }

        return $lateness + $this->absenceDays($absence) + $this->abandonmentDays($abandonment) + $this->unpaidLeaveDays($unpaidLeave);
    }
}

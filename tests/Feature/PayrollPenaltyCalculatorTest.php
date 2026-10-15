<?php

namespace Tests\Feature;

use App\Services\PayrollPenaltyCalculator;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PayrollPenaltyCalculatorTest extends TestCase
{
    #[TestWith([0, 0.0])]
    #[TestWith([30, 0.0])]
    #[TestWith([31, 0.5])]
    #[TestWith([45, 0.5])]
    #[TestWith([46, 1.0])]
    #[TestWith([60, 1.0])]
    #[TestWith([61, 2.0])]
    #[TestWith([90, 2.0])]
    #[TestWith([91, 3.0])]
    #[TestWith([120, 3.0])]
    public function test_monthly_lateness_penalties_follow_the_provided_table(int $minutes, float $expected): void
    {
        $this->assertSame($expected, (new PayrollPenaltyCalculator)->latenessDays($minutes));
    }

    public function test_121_minutes_requires_a_confirmed_first_occurrence(): void
    {
        $calculator = new PayrollPenaltyCalculator;

        $this->assertNull($calculator->latenessDays(121));
        $this->assertNull($calculator->latenessDays(180, false));
        $this->assertSame(4.0, $calculator->latenessDays(121, true));
    }

    public function test_absence_is_doubled_and_other_discounts_are_one_to_one(): void
    {
        $calculator = new PayrollPenaltyCalculator;

        $this->assertSame(1.0, $calculator->absenceDays(0.5));
        $this->assertSame(4.0, $calculator->absenceDays(2));
        $this->assertSame(0.5, $calculator->abandonmentDays(0.5));
        $this->assertSame(2.0, $calculator->unpaidLeaveDays(2));
        $this->assertSame(5.0, $calculator->totalDays(0.5, 1, 0.5, 2));
        $this->assertNull($calculator->totalDays(0.5, null, 0.5, 2));
    }
}

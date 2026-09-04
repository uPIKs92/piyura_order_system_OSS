<?php

namespace Tests\Unit;

use App\Support\PphUkm;
use Tests\TestCase;

class PphUkmTest extends TestCase
{
    public function test_mode_validation(): void
    {
        $this->assertTrue(PphUkm::isValidMode(PphUkm::MODE_NON_PKP));
        $this->assertTrue(PphUkm::isValidMode(PphUkm::MODE_PKP_22));
        $this->assertFalse(PphUkm::isValidMode('ppn'));
        $this->assertFalse(PphUkm::isValidMode(''));
    }

    public function test_non_pkp_below_tier_charged_half_percent_each_month(): void
    {
        $this->assertSame(0.0, PphUkm::annualTax(0));
        $this->assertEqualsWithDelta(2500000.0, PphUkm::annualTax(500000000), 0.0001);
        $this->assertSame(50000.0, PphUkm::monthlyTax(10000000, 0, PphUkm::MODE_NON_PKP));
        $this->assertSame(50000.0, PphUkm::monthlyTax(10000000, 40000000, PphUkm::MODE_NON_PKP));
        $this->assertSame(50000.0, PphUkm::monthlyTax(10000000, 400000000, PphUkm::MODE_NON_PKP));
    }

    public function test_non_pkp_crossing_tier_mid_year_splits_half_and_twelve_percent(): void
    {
        // Kumulatif per masa: 200jt, 350jt, 450jt, 550jt (lintas tier), 600jt.
        $series = PphUkm::monthlySeries(
            [200000000, 150000000, 100000000, 100000000, 50000000],
            PphUkm::MODE_NON_PKP,
        );

        $this->assertSame(
            [1000000.0, 750000.0, 500000.0, 6250000.0, 6000000.0],
            $series,
        );

        // Masa lintas tier: 0,5% x 50jt sisa tier + 12% x 50jt kelebihan.
        $this->assertEqualsWithDelta(2250000.0, PphUkm::annualTax(450000000), 0.0001);
        $this->assertEqualsWithDelta(8500000.0, PphUkm::annualTax(550000000), 0.0001);
        $this->assertEqualsWithDelta(14500000.0, PphUkm::annualTax(600000000), 0.0001);
        $this->assertSame(6250000.0, PphUkm::monthlyTax(100000000, 450000000, PphUkm::MODE_NON_PKP));
        $this->assertSame(6000000.0, PphUkm::monthlyTax(50000000, 550000000, PphUkm::MODE_NON_PKP));

        // Total per masa selalu sama dengan f(kumulatif akhir).
        $this->assertEqualsWithDelta(14500000.0, array_sum($series), 0.0001);
    }

    public function test_non_pkp_exact_fractional_amounts(): void
    {
        $this->assertSame(61.73, PphUkm::monthlyTax(12345.55, 0, PphUkm::MODE_NON_PKP));
        $this->assertSame(303.0, PphUkm::monthlyTax(60600.75, 0, PphUkm::MODE_NON_PKP));
        $this->assertSame(101.0, PphUkm::monthlyTax(20200.25, 60600.75, PphUkm::MODE_NON_PKP));
    }

    public function test_pkp_mode_charges_flat_monthly_rate_regardless_of_cumulative(): void
    {
        $this->assertSame(25000.0, PphUkm::monthlyTax(1000000, 0, PphUkm::MODE_PKP_22));
        $this->assertSame(25000.0, PphUkm::monthlyTax(1000000, 499999999, PphUkm::MODE_PKP_22));
        $this->assertSame(25000.01, PphUkm::monthlyTax(1000000.55, 123456.78, PphUkm::MODE_PKP_22));
    }
}

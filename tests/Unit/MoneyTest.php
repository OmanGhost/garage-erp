<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\Totals;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_rounds_half_up_to_three_decimals(): void
    {
        $this->assertSame('1.235', Money::round('1.2345'));
        $this->assertSame('1.234', Money::round('1.2344'));
        $this->assertSame('-1.235', Money::round('-1.2345'));
        $this->assertSame('0.000', Money::round(null));
    }

    public function test_arithmetic_has_no_float_drift(): void
    {
        $this->assertSame('0.300', Money::add('0.1', '0.2'));
        $this->assertSame('100.000', Money::sum(array_fill(0, 1000, '0.1')));
        $this->assertSame('5.250', Money::percent('105', '5'));
        $this->assertSame('3.333', Money::div('10', '3'));
        $this->assertSame(0, Money::cmp('1.000', '1'));
        $this->assertTrue(Money::isZero('0.000'));
        $this->assertFalse(Money::isZero('0.001'));
    }

    public function test_line_totals_apply_discount_before_vat(): void
    {
        // 3 x 10.000 - 2.000 discount = 28.000 net, VAT 5% = 1.400, gross 29.400
        $l = Totals::line(3, 10, 2, 5);
        $this->assertSame('28.000', Money::round($l['net']));
        $this->assertSame('1.400', Money::round($l['tax']));
        $this->assertSame('29.400', Money::round($l['total']));
    }
}

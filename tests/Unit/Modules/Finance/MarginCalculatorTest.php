<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use InvalidArgumentException;
use QS\Modules\Finance\Domain\Service\MarginCalculator;
use QS\Shared\Testing\TestCase;

final class MarginCalculatorTest extends TestCase
{
    private MarginCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new MarginCalculator();
    }

    public function testCalculatesPositiveMargin(): void
    {
        self::assertSame(30000, $this->calculator->calculate(90000, 60000));
    }

    public function testCalculatesZeroMarginWhenRevenueEqualsStaffCost(): void
    {
        self::assertSame(0, $this->calculator->calculate(60000, 60000));
    }

    public function testCalculatesNegativeMarginWhenCostExceedsRevenue(): void
    {
        self::assertSame(-10000, $this->calculator->calculate(50000, 60000));
    }

    public function testCalculatesMarginWithZeroRevenue(): void
    {
        self::assertSame(-40000, $this->calculator->calculate(0, 40000));
    }

    public function testCalculatesMarginWithZeroStaffCost(): void
    {
        self::assertSame(70000, $this->calculator->calculate(70000, 0));
    }

    public function testThrowsWhenStaffCostIsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A margin can not be calculated without staff cost.');

        $this->calculator->calculate(90000, null);
    }
}

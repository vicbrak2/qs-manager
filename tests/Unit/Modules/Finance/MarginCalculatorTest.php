<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use InvalidArgumentException;
use QS\Modules\Finance\Domain\Service\MarginCalculator;
use QS\Shared\Testing\TestCase;

final class MarginCalculatorTest extends TestCase
{
    public function testCalculatesMarginWhenCostIsDefined(): void
    {
        $calculator = new MarginCalculator();

        self::assertSame(30000, $calculator->calculate(90000, 60000));
    }

    public function testThrowsWhenCostIsMissing(): void
    {
        $calculator = new MarginCalculator();

        $this->expectException(InvalidArgumentException::class);
        $calculator->calculate(90000, null);
    }
}

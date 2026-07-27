<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Finance;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Finance\FinancialMetrics;
use QSManager\Domain\Finance\Money;

final class FinancialMetricsTest extends TestCase
{
    public function testNetResultDoesNotGoBelowZeroWhenFixedExpensesAreNotCovered(): void
    {
        $metrics = new FinancialMetrics(
            Money::fromInt(217530),
            Money::fromInt(165000),
            Money::fromInt(370000),
            Money::fromInt(105000),
            Money::fromInt(0),
            Money::fromInt(0),
            Money::fromInt(309110),
            Money::fromInt(0),
        );

        self::assertSame(0, $metrics->netResult()->amount());
        self::assertSame(0.0, $metrics->operatingMargin());
        self::assertSame(0, $metrics->toArray()['net_result']);
    }

    public function testNetResultKeepsOnlyTheSurplusAfterExpenses(): void
    {
        $metrics = new FinancialMetrics(
            Money::fromInt(600000),
            Money::fromInt(600000),
            Money::fromInt(0),
            Money::fromInt(600000),
            Money::fromInt(100000),
            Money::fromInt(50000),
            Money::fromInt(300000),
            Money::fromInt(0),
        );

        self::assertSame(150000, $metrics->netResult()->amount());
        self::assertSame(0.25, $metrics->operatingMargin());
    }
}

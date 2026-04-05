<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use QS\Modules\Finance\Domain\Service\MonthlySummaryBuilder;
use QS\Shared\Testing\TestCase;

final class MonthlySummaryBuilderTest extends TestCase
{
    private MonthlySummaryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MonthlySummaryBuilder();
    }

    public function testBuildsLedgerWithCorrectMonth(): void
    {
        $ledger = $this->builder->build('2026-04', 200000, 150000, 30000, 80000, 3, 2, 1, 0);

        self::assertSame('2026-04', $ledger->month());
    }

    public function testCalculatesProjectedMarginCorrectly(): void
    {
        $ledger = $this->builder->build('2026-04', 200000, 150000, 30000, 80000, 3, 2, 1, 0);

        self::assertSame(90000, $ledger->projectedMarginClp());
    }

    public function testCalculatesRealizedMarginCorrectly(): void
    {
        $ledger = $this->builder->build('2026-04', 200000, 150000, 30000, 80000, 3, 2, 1, 0);

        self::assertSame(40000, $ledger->realizedMarginClp());
    }

    public function testPreservesCounters(): void
    {
        $ledger = $this->builder->build('2026-04', 200000, 150000, 30000, 80000, 5, 3, 2, 1);

        self::assertSame(5, $ledger->serviceCount());
        self::assertSame(3, $ledger->paymentCount());
        self::assertSame(2, $ledger->expenseCount());
        self::assertSame(1, $ledger->missingCostServicesCount());
    }

    public function testSupportsNegativeMargins(): void
    {
        $ledger = $this->builder->build('2026-04', 50000, 40000, 30000, 80000, 1, 1, 1, 0);

        self::assertSame(-60000, $ledger->projectedMarginClp());
        self::assertSame(-70000, $ledger->realizedMarginClp());
    }

    public function testSupportsZeroValues(): void
    {
        $ledger = $this->builder->build('2026-04', 0, 0, 0, 0, 0, 0, 0, 0);

        self::assertSame(0, $ledger->projectedMarginClp());
        self::assertSame(0, $ledger->realizedMarginClp());
    }

    public function testPreservesRevenueAndCostValues(): void
    {
        $ledger = $this->builder->build('2026-04', 200000, 150000, 30000, 80000, 3, 2, 1, 0);

        self::assertSame(200000, $ledger->bookedRevenueClp());
        self::assertSame(150000, $ledger->paidRevenueClp());
        self::assertSame(30000, $ledger->expensesClp());
        self::assertSame(80000, $ledger->staffCostClp());
    }
}

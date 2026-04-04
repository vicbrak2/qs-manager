<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use QS\Modules\Finance\Domain\Service\MonthlySummaryBuilder;
use QS\Shared\Testing\TestCase;

final class MonthlySummaryBuilderTest extends TestCase
{
    public function testBuildsMonthlyLedgerWithProjectedAndRealizedMargins(): void
    {
        $builder = new MonthlySummaryBuilder();
        $ledger = $builder->build('2026-04', 270000, 180000, 20000, 120000, 3, 2, 1, 1);

        self::assertSame('2026-04', $ledger->month());
        self::assertSame(130000, $ledger->projectedMarginClp());
        self::assertSame(40000, $ledger->realizedMarginClp());
        self::assertSame(1, $ledger->missingCostServicesCount());
    }
}

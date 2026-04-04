<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Service;

use QS\Modules\Finance\Domain\Entity\MonthlyLedger;

final class MonthlySummaryBuilder
{
    public function build(
        string $month,
        int $bookedRevenueClp,
        int $paidRevenueClp,
        int $expensesClp,
        int $staffCostClp,
        int $serviceCount,
        int $paymentCount,
        int $expenseCount,
        int $missingCostServicesCount
    ): MonthlyLedger {
        return new MonthlyLedger(
            $month,
            $bookedRevenueClp,
            $paidRevenueClp,
            $expensesClp,
            $staffCostClp,
            $bookedRevenueClp - $staffCostClp - $expensesClp,
            $paidRevenueClp - $staffCostClp - $expensesClp,
            $serviceCount,
            $paymentCount,
            $expenseCount,
            $missingCostServicesCount
        );
    }
}

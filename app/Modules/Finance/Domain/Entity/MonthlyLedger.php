<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Entity;

final class MonthlyLedger
{
    public function __construct(
        private readonly string $month,
        private readonly int $bookedRevenueClp,
        private readonly int $paidRevenueClp,
        private readonly int $expensesClp,
        private readonly int $staffCostClp,
        private readonly int $projectedMarginClp,
        private readonly int $realizedMarginClp,
        private readonly int $serviceCount,
        private readonly int $paymentCount,
        private readonly int $expenseCount,
        private readonly int $missingCostServicesCount
    ) {
    }

    public function month(): string
    {
        return $this->month;
    }

    public function bookedRevenueClp(): int
    {
        return $this->bookedRevenueClp;
    }

    public function paidRevenueClp(): int
    {
        return $this->paidRevenueClp;
    }

    public function expensesClp(): int
    {
        return $this->expensesClp;
    }

    public function staffCostClp(): int
    {
        return $this->staffCostClp;
    }

    public function projectedMarginClp(): int
    {
        return $this->projectedMarginClp;
    }

    public function realizedMarginClp(): int
    {
        return $this->realizedMarginClp;
    }

    public function serviceCount(): int
    {
        return $this->serviceCount;
    }

    public function paymentCount(): int
    {
        return $this->paymentCount;
    }

    public function expenseCount(): int
    {
        return $this->expenseCount;
    }

    public function missingCostServicesCount(): int
    {
        return $this->missingCostServicesCount;
    }
}

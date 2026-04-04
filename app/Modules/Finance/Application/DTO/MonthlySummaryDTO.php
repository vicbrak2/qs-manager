<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\DTO;

use QS\Modules\Finance\Domain\Entity\MonthlyLedger;

final class MonthlySummaryDTO
{
    public function __construct(private readonly MonthlyLedger $monthlyLedger)
    {
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'month' => $this->monthlyLedger->month(),
            'booked_revenue_clp' => $this->monthlyLedger->bookedRevenueClp(),
            'paid_revenue_clp' => $this->monthlyLedger->paidRevenueClp(),
            'expenses_clp' => $this->monthlyLedger->expensesClp(),
            'staff_cost_clp' => $this->monthlyLedger->staffCostClp(),
            'projected_margin_clp' => $this->monthlyLedger->projectedMarginClp(),
            'realized_margin_clp' => $this->monthlyLedger->realizedMarginClp(),
            'service_count' => $this->monthlyLedger->serviceCount(),
            'payment_count' => $this->monthlyLedger->paymentCount(),
            'expense_count' => $this->monthlyLedger->expenseCount(),
            'missing_cost_services_count' => $this->monthlyLedger->missingCostServicesCount(),
        ];
    }
}

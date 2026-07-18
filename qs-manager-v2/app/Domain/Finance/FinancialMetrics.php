<?php

declare(strict_types=1);

namespace QSManager\Domain\Finance;

final class FinancialMetrics
{
    public function __construct(
        public readonly Money $contractedSales,
        public readonly Money $collectedRevenue,
        public readonly Money $directCosts,
        public readonly Money $operatingExpenses,
        public readonly Money $refunds,
    ) {
    }

    public function accountsReceivable(): Money
    {
        $amount = $this->contractedSales->amount() 
            - $this->collectedRevenue->amount() 
            - $this->refunds->amount();
        
        return Money::fromInt(max($amount, 0));
    }

    public function netResult(): Money
    {
        return $this->collectedRevenue
            ->subtract($this->directCosts)
            ->subtract($this->operatingExpenses)
            ->subtract($this->refunds);
    }

    public function operatingMargin(): ?float
    {
        $revenue = $this->collectedRevenue->amount();
        if ($revenue === 0) {
            return null;
        }

        return round($this->netResult()->amount() / $revenue, 4);
    }

    public function toArray(): array
    {
        return [
            'contracted_sales' => $this->contractedSales->amount(),
            'collected_revenue' => $this->collectedRevenue->amount(),
            'accounts_receivable' => $this->accountsReceivable()->amount(),
            'direct_costs' => $this->directCosts->amount(),
            'operating_expenses' => $this->operatingExpenses->amount(),
            'refunds' => $this->refunds->amount(),
            'net_result' => $this->netResult()->amount(),
            'operating_margin' => $this->operatingMargin(),
        ];
    }
}

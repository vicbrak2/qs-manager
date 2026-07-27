<?php

declare(strict_types=1);

namespace QSManager\Domain\Finance;

final class FinancialMetrics
{
    public function __construct(
        public readonly Money $contractedSales,
        public readonly Money $collectedRevenue,
        public readonly Money $committedDeposits,
        public readonly Money $realizedRevenue,
        public readonly Money $directCosts,
        public readonly Money $operatingExpenses,
        public readonly Money $fixedExpenses,
        public readonly Money $refunds,
        private readonly ?Money $accountsReceivable = null,
    ) {
    }

    public function accountsReceivable(): Money
    {
        if ($this->accountsReceivable !== null) {
            return $this->accountsReceivable;
        }

        $amount = $this->contractedSales->amount() 
            - $this->collectedRevenue->amount() 
            - $this->refunds->amount();
        
        return Money::fromInt(max($amount, 0));
    }

    public function netResult(): Money
    {
        $rawResult = $this->realizedRevenue
            ->subtract($this->directCosts)
            ->subtract($this->operatingExpenses)
            ->subtract($this->fixedExpenses)
            ->subtract($this->refunds);

        return Money::fromInt(max(0, $rawResult->amount()));
    }

    public function operatingMargin(): ?float
    {
        $revenue = $this->realizedRevenue->amount();
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
            'committed_deposits' => $this->committedDeposits->amount(),
            'realized_revenue' => $this->realizedRevenue->amount(),
            'accounts_receivable' => $this->accountsReceivable()->amount(),
            'direct_costs' => $this->directCosts->amount(),
            'operating_expenses' => $this->operatingExpenses->amount(),
            'fixed_expenses' => $this->fixedExpenses->amount(),
            'refunds' => $this->refunds->amount(),
            'net_result' => $this->netResult()->amount(),
            'operating_margin' => $this->operatingMargin(),
        ];
    }
}

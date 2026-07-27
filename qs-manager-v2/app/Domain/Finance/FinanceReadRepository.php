<?php

declare(strict_types=1);

namespace QSManager\Domain\Finance;

interface FinanceReadRepository
{
    public function dashboard(
        FinancePeriod $period,
        AccountingBasis $basis
    ): FinancialMetrics;

    public function cashFlow(
        FinancePeriod $period,
        AccountingBasis $basis,
        string $granularity = 'month'
    ): array;

    public function expenses(
        FinancePeriod $period,
        ?string $status = null,
        ?string $category = null,
        int $page = 1,
        int $perPage = 50
    ): array;

    public function reconciliation(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function quality(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function availableDetails(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function fixedExpenseDetails(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function contractedSalesDetails(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function collectedRevenueDetails(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function committedDepositsDetails(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function releasedRevenueDetails(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;

    public function accountsReceivableDetails(
        FinancePeriod $period,
        AccountingBasis $basis
    ): array;
}


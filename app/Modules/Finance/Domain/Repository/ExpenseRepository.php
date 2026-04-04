<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Repository;

use QS\Modules\Finance\Domain\Entity\Expense;

interface ExpenseRepository
{
    /**
     * @return array<int, Expense>
     */
    public function findAll(): array;

    /**
     * @return array<int, Expense>
     */
    public function findByMonth(string $month): array;
}

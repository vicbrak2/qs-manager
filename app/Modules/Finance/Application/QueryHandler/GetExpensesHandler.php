<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\QueryHandler;

use QS\Modules\Finance\Application\DTO\ExpenseDTO;
use QS\Modules\Finance\Application\Query\GetExpenses;
use QS\Modules\Finance\Domain\Repository\ExpenseRepository;

final class GetExpensesHandler
{
    public function __construct(private readonly ExpenseRepository $expenseRepository)
    {
    }

    /**
     * @return array<int, ExpenseDTO>
     */
    public function handle(GetExpenses $query): array
    {
        $expenses = $query->month !== null
            ? $this->expenseRepository->findByMonth($query->month)
            : $this->expenseRepository->findAll();

        return array_map(
            static fn ($expense): ExpenseDTO => new ExpenseDTO($expense),
            $expenses
        );
    }
}

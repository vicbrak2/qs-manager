<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\DTO;

use QS\Modules\Finance\Domain\Entity\Expense;

final class ExpenseDTO
{
    public function __construct(private readonly Expense $expense)
    {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->expense->id(),
            'concept' => $this->expense->concept(),
            'amount_clp' => $this->expense->amount()->amount(),
            'category' => $this->expense->category(),
            'month' => $this->expense->month(),
            'receipt_url' => $this->expense->receiptUrl(),
            'created_at' => $this->expense->createdAt()->format(DATE_ATOM),
        ];
    }
}

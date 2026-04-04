<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Entity;

use DateTimeImmutable;
use QS\Shared\ValueObjects\Money;

final class Expense
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $concept,
        private readonly Money $amount,
        private readonly string $category,
        private readonly string $month,
        private readonly ?string $receiptUrl,
        private readonly DateTimeImmutable $createdAt
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function concept(): string
    {
        return $this->concept;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function month(): string
    {
        return $this->month;
    }

    public function receiptUrl(): ?string
    {
        return $this->receiptUrl;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Entity;

use DateTimeImmutable;
use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;
use QS\Shared\ValueObjects\Money;

final class Payment
{
    public function __construct(
        private readonly ?int $id,
        private readonly ?int $reservationId,
        private readonly ?string $concept,
        private readonly Money $amount,
        private readonly PaymentMethod $method,
        private readonly string $status,
        private readonly DateTimeImmutable $paidAt,
        private readonly string $closingMonth
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function reservationId(): ?int
    {
        return $this->reservationId;
    }

    public function concept(): ?string
    {
        return $this->concept;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function paidAt(): DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function closingMonth(): string
    {
        return $this->closingMonth;
    }
}

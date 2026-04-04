<?php

declare(strict_types=1);

namespace QS\Shared\ValueObjects;

use QS\Shared\Domain\ValueObject;

final class Money extends ValueObject
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency = 'CLP'
    ) {
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    protected function toPrimitives(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}

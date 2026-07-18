<?php

declare(strict_types=1);

namespace QSManager\Domain\Finance;

use InvalidArgumentException;

/**
 * Value Object to represent monetary amounts in CLP.
 * Operates on integers to avoid floating point inaccuracies.
 */
final class Money
{
    private function __construct(private readonly int $amount)
    {
    }

    public static function fromInt(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function add(Money $other): self
    {
        return new self($this->amount + $other->amount());
    }

    public function subtract(Money $other): self
    {
        return new self($this->amount - $other->amount());
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amount * $multiplier);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount();
    }

    public function greaterThan(Money $other): bool
    {
        return $this->amount > $other->amount();
    }

    public function lessThan(Money $other): bool
    {
        return $this->amount < $other->amount();
    }
}

<?php

declare(strict_types=1);

namespace QSManager\Domain\Booking;

use InvalidArgumentException;

final class BookingId
{
    private function __construct(private readonly int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Booking id must be greater than zero.');
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }
}

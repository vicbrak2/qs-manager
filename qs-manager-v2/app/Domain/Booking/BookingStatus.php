<?php

declare(strict_types=1);

namespace QSManager\Domain\Booking;

use InvalidArgumentException;

final class BookingStatus
{
    private const ALLOWED = [
        'draft',
        'confirmed',
        'cancelled',
        'completed',
    ];

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = strtolower(trim($value));

        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(
                'Booking status must be one of: ' . implode(', ', self::ALLOWED) . '.'
            );
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}

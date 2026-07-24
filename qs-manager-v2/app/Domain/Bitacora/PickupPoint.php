<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use InvalidArgumentException;

final class PickupPoint
{
    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Pickup point is required.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

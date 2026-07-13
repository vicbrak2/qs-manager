<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

use InvalidArgumentException;

final class StaffId
{
    private function __construct(private readonly int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Staff id must be greater than zero.');
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }
}


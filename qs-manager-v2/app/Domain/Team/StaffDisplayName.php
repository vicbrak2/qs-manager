<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

use InvalidArgumentException;

final class StaffDisplayName
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Staff display name is required.');
        }

        if (mb_strlen($value) < 3) {
            throw new InvalidArgumentException('Staff display name must be at least 3 characters.');
        }

        if (mb_strlen($value) > 160) {
            throw new InvalidArgumentException('Staff display name cannot exceed 160 characters.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}

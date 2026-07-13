<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

use InvalidArgumentException;

final class StaffRole
{
    private const ALLOWED = [
        'admin',
        'coordinadora',
        'staff',
    ];

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = strtolower(trim($value));

        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(
                'Staff role must be one of: ' . implode(', ', self::ALLOWED) . '.'
            );
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}


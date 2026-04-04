<?php

declare(strict_types=1);

namespace QS\Shared\ValueObjects;

use QS\Shared\Domain\ValueObject;

final class UserId extends ValueObject
{
    public function __construct(private readonly int $value)
    {
    }

    public function value(): int
    {
        return $this->value;
    }

    protected function toPrimitives(): array
    {
        return ['value' => $this->value];
    }
}

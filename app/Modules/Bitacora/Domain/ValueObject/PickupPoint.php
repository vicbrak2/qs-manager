<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Domain\ValueObject;

use InvalidArgumentException;
use QS\Shared\Domain\ValueObject;

final class PickupPoint extends ValueObject
{
    private string $value;

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

    protected function toPrimitives(): array
    {
        return [
            'value' => $this->value,
        ];
    }
}

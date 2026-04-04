<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Domain\ValueObject;

use InvalidArgumentException;
use QS\Shared\Domain\ValueObject;

final class TravelDuration extends ValueObject
{
    public function __construct(private readonly int $minutes)
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Travel duration can not be negative.');
        }
    }

    public function minutes(): int
    {
        return $this->minutes;
    }

    public function meetsRecommendedMinimum(): bool
    {
        return $this->minutes >= 15;
    }

    protected function toPrimitives(): array
    {
        return [
            'minutes' => $this->minutes,
        ];
    }
}

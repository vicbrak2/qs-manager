<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use InvalidArgumentException;

final class TravelDuration
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

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}

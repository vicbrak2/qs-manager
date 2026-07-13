<?php

declare(strict_types=1);

namespace QSManager\Domain\ServicesCatalog;

use InvalidArgumentException;

final class ServiceDuration
{
    private function __construct(private readonly int $minutes)
    {
    }

    public static function fromMinutes(int $minutes): self
    {
        if ($minutes <= 0) {
            throw new InvalidArgumentException('Service duration must be greater than zero minutes.');
        }

        if ($minutes > 1440) {
            throw new InvalidArgumentException('Service duration cannot exceed 1440 minutes.');
        }

        return new self($minutes);
    }

    public function minutes(): int
    {
        return $this->minutes;
    }
}


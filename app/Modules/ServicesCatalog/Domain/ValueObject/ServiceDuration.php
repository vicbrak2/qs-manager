<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Domain\ValueObject;

use InvalidArgumentException;

final class ServiceDuration
{
    public function __construct(
        private readonly int $durationMin,
        private readonly int $bufferMin
    ) {
        if ($this->durationMin <= 0) {
            throw new InvalidArgumentException('Service duration must be greater than zero.');
        }

        if ($this->bufferMin < 0) {
            throw new InvalidArgumentException('Service buffer can not be negative.');
        }
    }

    public function durationMin(): int
    {
        return $this->durationMin;
    }

    public function bufferMin(): int
    {
        return $this->bufferMin;
    }

    public function totalMin(): int
    {
        return $this->durationMin + $this->bufferMin;
    }
}

<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Domain\ValueObject;

use InvalidArgumentException;

final class ServicePrice
{
    public function __construct(private readonly int $priceClp)
    {
        if ($this->priceClp <= 0) {
            throw new InvalidArgumentException('Service price must be greater than zero.');
        }
    }

    public function amount(): int
    {
        return $this->priceClp;
    }
}

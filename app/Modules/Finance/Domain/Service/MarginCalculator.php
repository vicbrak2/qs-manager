<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Service;

use InvalidArgumentException;

final class MarginCalculator
{
    public function calculate(int $revenueClp, ?int $staffCostClp): int
    {
        if ($staffCostClp === null) {
            throw new InvalidArgumentException('A margin can not be calculated without staff cost.');
        }

        return $revenueClp - $staffCostClp;
    }
}

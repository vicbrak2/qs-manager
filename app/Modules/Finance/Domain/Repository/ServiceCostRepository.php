<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Repository;

interface ServiceCostRepository
{
    /**
     * @return array<string, int>
     */
    public function findAll(): array;
}

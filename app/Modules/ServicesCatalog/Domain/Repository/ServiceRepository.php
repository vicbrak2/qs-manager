<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Domain\Repository;

use QS\Modules\ServicesCatalog\Domain\Entity\Service;

interface ServiceRepository
{
    /**
     * @return array<int, Service>
     */
    public function findAll(bool $activeOnly = true): array;

    public function findById(int $id): ?Service;
}

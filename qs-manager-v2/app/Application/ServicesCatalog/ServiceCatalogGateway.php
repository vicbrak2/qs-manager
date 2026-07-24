<?php

declare(strict_types=1);

namespace QSManager\Application\ServicesCatalog;

interface ServiceCatalogGateway
{
    /**
     * @param array<string, mixed> $service
     * @return array<string, mixed>
     */
    public function create(array $service, string $idempotencyKey): array;
}

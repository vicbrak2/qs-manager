<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Application\QueryHandler;

use QS\Modules\ServicesCatalog\Application\DTO\ServiceDTO;
use QS\Modules\ServicesCatalog\Application\Query\GetAllServices;
use QS\Modules\ServicesCatalog\Domain\Repository\ServiceRepository;

final class GetAllServicesHandler
{
    public function __construct(private readonly ServiceRepository $serviceRepository)
    {
    }

    /**
     * @return array<int, ServiceDTO>
     */
    public function handle(GetAllServices $query): array
    {
        return array_map(
            static fn ($service): ServiceDTO => new ServiceDTO($service),
            $this->serviceRepository->findAll($query->activeOnly)
        );
    }
}

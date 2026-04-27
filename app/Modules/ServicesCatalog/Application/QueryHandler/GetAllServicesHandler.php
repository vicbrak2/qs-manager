<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Application\QueryHandler;

use QS\Modules\ServicesCatalog\Application\DTO\ServiceDTO;
use QS\Modules\ServicesCatalog\Application\Query\GetAllServices;
use QS\Modules\ServicesCatalog\Domain\Repository\ServiceRepository;
use QS\Shared\Bus\QueryHandlerInterface;

final class GetAllServicesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ServiceRepository $serviceRepository)
    {
    }

    /**
     * @return array<int, ServiceDTO>
     */
    public function handle(object $query): array
    {
        assert($query instanceof GetAllServices);

        return array_map(
            static fn ($service): ServiceDTO => new ServiceDTO($service),
            $this->serviceRepository->findAll($query->activeOnly)
        );
    }
}

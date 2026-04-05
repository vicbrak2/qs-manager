<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Application\QueryHandler;

use QS\Modules\ServicesCatalog\Application\DTO\ServiceDTO;
use QS\Modules\ServicesCatalog\Application\Query\GetServiceById;
use QS\Modules\ServicesCatalog\Domain\Repository\ServiceRepository;

final class GetServiceByIdHandler
{
    public function __construct(private readonly ServiceRepository $serviceRepository)
    {
    }

    public function handle(GetServiceById $query): ?ServiceDTO
    {
        $service = $this->serviceRepository->findById($query->id);

        return $service !== null ? new ServiceDTO($service) : null;
    }
}

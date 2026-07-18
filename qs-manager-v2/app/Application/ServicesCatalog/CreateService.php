<?php

declare(strict_types=1);

namespace QSManager\Application\ServicesCatalog;

use QSManager\Domain\ServicesCatalog\Service;
use QSManager\Domain\ServicesCatalog\ServiceRepository;

final class CreateService
{
    public function __construct(private readonly ServiceRepository $services)
    {
    }

    public function execute(CreateServiceCommand $command): Service
    {
        $service = Service::create(
            $command->name,
            $command->category,
            $command->durationMinutes,
            $command->quantity,
        );

        return $this->services->save($service);
    }
}

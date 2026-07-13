<?php

declare(strict_types=1);

namespace QSManager\Application\ServicesCatalog;

use QSManager\Domain\ServicesCatalog\ServiceRepository;

final class ListServices
{
    public function __construct(private readonly ServiceRepository $services)
    {
    }

    public function execute(): array
    {
        return $this->services->findAll();
    }
}


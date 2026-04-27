<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog;

use function DI\autowire;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\ServicesCatalog\Application\Query\GetAllServices;
use QS\Modules\ServicesCatalog\Application\Query\GetServiceById;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetAllServicesHandler;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetServiceByIdHandler;
use QS\Modules\ServicesCatalog\Domain\Repository\ServiceRepository;
use QS\Modules\ServicesCatalog\Infrastructure\Persistence\WpdbServiceCatalogRepository;
use QS\Modules\ServicesCatalog\Interfaces\Rest\ServicesController;

final class ServicesCatalogServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            ServiceRepository::class => autowire(WpdbServiceCatalogRepository::class),
            GetAllServicesHandler::class => autowire(),
            GetServiceByIdHandler::class => autowire(),
            ServicesController::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [];
    }

    public static function queryHandlers(): array
    {
        return [
            GetAllServices::class => GetAllServicesHandler::class,
            GetServiceById::class => GetServiceByIdHandler::class,
        ];
    }

    public static function hookables(): array
    {
        return [];
    }

    public static function activationHooks(): array
    {
        return [];
    }
}

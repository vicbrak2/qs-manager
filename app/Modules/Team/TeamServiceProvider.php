<?php

declare(strict_types=1);

namespace QS\Modules\Team;

use function DI\autowire;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\Team\Application\Query\GetAllStaff;
use QS\Modules\Team\Application\Query\GetStaffAvailability;
use QS\Modules\Team\Application\Query\GetStaffById;
use QS\Modules\Team\Application\QueryHandler\GetAllStaffHandler;
use QS\Modules\Team\Application\QueryHandler\GetStaffAvailabilityHandler;
use QS\Modules\Team\Application\QueryHandler\GetStaffByIdHandler;
use QS\Modules\Team\Domain\Repository\StaffRepository;
use QS\Modules\Team\Domain\Service\AvailabilityChecker;
use QS\Modules\Team\Infrastructure\Persistence\WpdbStaffRepository;
use QS\Modules\Team\Interfaces\Rest\StaffController;

final class TeamServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            StaffRepository::class => autowire(WpdbStaffRepository::class),
            AvailabilityChecker::class => autowire(),
            GetAllStaffHandler::class => autowire(),
            GetStaffByIdHandler::class => autowire(),
            GetStaffAvailabilityHandler::class => autowire(),
            StaffController::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [];
    }

    public static function queryHandlers(): array
    {
        return [
            GetAllStaff::class => GetAllStaffHandler::class,
            GetStaffAvailability::class => GetStaffAvailabilityHandler::class,
            GetStaffById::class => GetStaffByIdHandler::class,
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

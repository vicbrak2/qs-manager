<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess;

use function DI\autowire;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\IdentityAccess\Application\Command\AssignQsRole;
use QS\Modules\IdentityAccess\Application\CommandHandler\AssignQsRoleHandler;
use QS\Modules\IdentityAccess\Application\Query\GetUserPermissions;
use QS\Modules\IdentityAccess\Application\QueryHandler\GetUserPermissionsHandler;
use QS\Modules\IdentityAccess\Domain\Policy\AccessPolicy;
use QS\Modules\IdentityAccess\Domain\Repository\UserRepository;
use QS\Modules\IdentityAccess\Infrastructure\Persistence\WpUserRepository;
use QS\Modules\IdentityAccess\Infrastructure\Wordpress\RoleRegistrar;
use QS\Modules\IdentityAccess\Interfaces\Hooks\RoleHooks;

final class IdentityAccessServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            AccessPolicy::class => autowire(),
            UserRepository::class => autowire(WpUserRepository::class),
            RoleRegistrar::class => autowire(),
            RoleHooks::class => autowire(),
            AssignQsRoleHandler::class => autowire(),
            GetUserPermissionsHandler::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [
            AssignQsRole::class => AssignQsRoleHandler::class,
        ];
    }

    public static function queryHandlers(): array
    {
        return [
            GetUserPermissions::class => GetUserPermissionsHandler::class,
        ];
    }

    public static function hookables(): array
    {
        return [
            RoleRegistrar::class,
            RoleHooks::class,
        ];
    }

    public static function activationHooks(): array
    {
        return [
            RoleRegistrar::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace QS\Modules\Setup;

use function DI\autowire;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\Setup\Application\Command\SetupSiteCommand;
use QS\Modules\Setup\Application\CommandHandler\SetupSiteHandler;
use QS\Modules\Setup\Infrastructure\Wordpress\AgentStatusChecker;
use QS\Modules\Setup\Infrastructure\Wordpress\MenuProvisioner;
use QS\Modules\Setup\Infrastructure\Wordpress\OptionProvisioner;
use QS\Modules\Setup\Infrastructure\Wordpress\PageProvisioner;
use QS\Modules\Setup\Infrastructure\Wordpress\PermalinkProvisioner;
use QS\Modules\Setup\Interfaces\Cli\CliCommandRegistrar;
use QS\Modules\Setup\Interfaces\Cli\QsCommand;
use QS\Modules\Setup\Interfaces\Hooks\ActivationSetupHooks;
use QS\Modules\Setup\Interfaces\Rest\SetupController;

final class SetupServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            PageProvisioner::class => autowire(),
            OptionProvisioner::class => autowire(),
            MenuProvisioner::class => autowire(),
            PermalinkProvisioner::class => autowire(),
            AgentStatusChecker::class => autowire(),
            SetupSiteHandler::class => autowire(),
            QsCommand::class => autowire(),
            CliCommandRegistrar::class => autowire(),
            ActivationSetupHooks::class => autowire(),
            SetupController::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [
            SetupSiteCommand::class => SetupSiteHandler::class,
        ];
    }

    public static function queryHandlers(): array
    {
        return [];
    }

    public static function hookables(): array
    {
        return [
            CliCommandRegistrar::class,
        ];
    }

    public static function activationHooks(): array
    {
        return [
            ActivationSetupHooks::class,
        ];
    }
}

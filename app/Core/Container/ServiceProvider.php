<?php

declare(strict_types=1);

namespace QS\Core\Container;

use function DI\autowire;
use function DI\factory;
use function DI\get;

use DI\Container as DiContainer;
use Psr\Container\ContainerInterface;
use QS\Core\Bootstrap\HookLoader;
use QS\Core\Bootstrap\ModuleRegistry;
use QS\Core\Config\ConfigLoader;
use QS\Core\Config\EnvironmentDetector;
use QS\Core\Config\PluginConfig;
use QS\Core\Contracts\ActivationHookInterface;
use QS\Core\Contracts\HookableInterface;
use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Core\Errors\ErrorHandler;
use QS\Core\Events\EventDispatcher;
use QS\Core\Logging\Logger;
use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\NonceManager;
use QS\Core\Security\RequestSanitizer;
use QS\Core\Versioning\MigrationRunner;
use QS\Core\Versioning\PluginVersion;
use QS\Core\Wordpress\PostTypeRegistrar;
use QS\Core\Wordpress\RestRouteRegistrar;
use QS\Interfaces\Rest\SystemController;
use QS\Shared\Bus\CommandBus;
use QS\Shared\Bus\QueryBus;
use QS\Shared\Clock\SystemClock;

final class ServiceProvider
{
    /**
     * @var list<class-string<ModuleServiceProviderInterface>>
     */
    public const MODULE_PROVIDERS = [
        \QS\Modules\Finance\FinanceServiceProvider::class,
        \QS\Modules\Booking\BookingServiceProvider::class,
        \QS\Modules\Bitacora\BitacoraServiceProvider::class,
        \QS\Modules\Team\TeamServiceProvider::class,
        \QS\Modules\ServicesCatalog\ServicesCatalogServiceProvider::class,
        \QS\Modules\IdentityAccess\IdentityAccessServiceProvider::class,
        \QS\Modules\Agents\AgentsServiceProvider::class,
        \QS\Modules\Setup\SetupServiceProvider::class,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function definitions(string $rootDir): array
    {
        $modules = [];

        foreach (self::MODULE_PROVIDERS as $providerClass) {
            $modules[] = $providerClass::definitions();
        }

        return array_merge(self::coreDefinitions($rootDir), ...$modules);
    }

    /**
     * @return array<class-string<HookableInterface>>
     */
    public static function hookables(): array
    {
        $hookables = [];

        foreach (self::MODULE_PROVIDERS as $providerClass) {
            foreach ($providerClass::hookables() as $hookableClass) {
                $hookables[] = $hookableClass;
            }
        }

        return $hookables;
    }

    /**
     * @return array<class-string<ActivationHookInterface>>
     */
    public static function activationHooks(): array
    {
        $activationHooks = [];

        foreach (self::MODULE_PROVIDERS as $providerClass) {
            foreach ($providerClass::activationHooks() as $activationHookClass) {
                $activationHooks[] = $activationHookClass;
            }
        }

        return $activationHooks;
    }

    /**
     * @return array<string, mixed>
     */
    private static function coreDefinitions(string $rootDir): array
    {
        return [
            \wpdb::class => factory(static function (): \wpdb {
                global $wpdb;

                return $wpdb;
            }),
            'plugin.root_dir' => $rootDir,
            EnvironmentDetector::class => autowire(),
            ConfigLoader::class => autowire()->constructor($rootDir, get(EnvironmentDetector::class)),
            PluginConfig::class => factory(
                static function (ConfigLoader $configLoader): PluginConfig {
                    $baseConfig = $configLoader->load('plugin.php');
                    $environmentConfig = $configLoader->loadEnvironmentConfig();

                    return new PluginConfig(array_replace_recursive($baseConfig, $environmentConfig));
                }
            ),
            PluginVersion::class => autowire(),
            HookLoader::class => autowire(),
            ModuleRegistry::class => factory(
                static function (ConfigLoader $configLoader): ModuleRegistry {
                    return new ModuleRegistry($configLoader->loadModuleConfigs());
                }
            ),
            ContainerInterface::class => factory(
                static function (DiContainer $container): ContainerInterface {
                    return $container;
                }
            ),
            'wordpress.post_types' => factory(
                static function (ConfigLoader $configLoader): array {
                    return $configLoader->load('wordpress/post-types.php');
                }
            ),
            Logger::class => autowire()->constructor(get('plugin.root_dir'), get(PluginConfig::class)),
            ErrorHandler::class => autowire(),
            EventDispatcher::class => autowire(),
            NonceManager::class => autowire(),
            CapabilityChecker::class => autowire(),
            RequestSanitizer::class => autowire(),
            SystemClock::class => autowire(),
            CommandBus::class => factory(static function (ContainerInterface $container): CommandBus {
                $bus = new CommandBus($container);

                foreach (self::MODULE_PROVIDERS as $providerClass) {
                    foreach ($providerClass::commandHandlers() as $commandClass => $handlerClass) {
                        $bus->register($commandClass, $handlerClass);
                    }
                }

                return $bus;
            }),
            QueryBus::class => factory(static function (ContainerInterface $container): QueryBus {
                $bus = new QueryBus($container);

                foreach (self::MODULE_PROVIDERS as $providerClass) {
                    foreach ($providerClass::queryHandlers() as $queryClass => $handlerClass) {
                        $bus->register($queryClass, $handlerClass);
                    }
                }

                return $bus;
            }),
            PostTypeRegistrar::class => autowire()->constructor(get('wordpress.post_types')),
            RestRouteRegistrar::class => autowire(),
            MigrationRunner::class => autowire()->constructor(
                $rootDir,
                get(PluginVersion::class),
                get(Logger::class)
            ),
            SystemController::class => autowire(),
        ];
    }
}

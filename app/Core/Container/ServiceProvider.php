<?php

declare(strict_types=1);

namespace QS\Core\Container;

use DI\autowire;
use DI\factory;
use DI\get;
use QS\Core\Bootstrap\HookLoader;
use QS\Core\Bootstrap\ModuleRegistry;
use QS\Core\Config\ConfigLoader;
use QS\Core\Config\EnvironmentDetector;
use QS\Core\Config\PluginConfig;
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
use QS\Core\Wordpress\RoleRegistrar;
use QS\Interfaces\Rest\SystemController;
use QS\Shared\Clock\SystemClock;

final class ServiceProvider
{
    /**
     * @return array<string, mixed>
     */
    public static function definitions(string $rootDir): array
    {
        return [
            'qs.root_dir' => $rootDir,
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
            Logger::class => autowire(),
            ErrorHandler::class => autowire(),
            EventDispatcher::class => autowire(),
            NonceManager::class => autowire(),
            CapabilityChecker::class => autowire(),
            RequestSanitizer::class => autowire(),
            SystemClock::class => autowire(),
            RoleRegistrar::class => autowire(),
            PostTypeRegistrar::class => autowire(),
            RestRouteRegistrar::class => autowire(),
            MigrationRunner::class => autowire()->constructor(
                $rootDir,
                get(PluginVersion::class),
                get(Logger::class),
                get(RoleRegistrar::class),
                get(PostTypeRegistrar::class)
            ),
            SystemController::class => autowire(),
        ];
    }
}

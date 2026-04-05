<?php

declare(strict_types=1);

namespace QS\Core\Bootstrap;

use DI\Container;
use QS\Core\Container\ContainerBuilder;
use QS\Core\Errors\ErrorHandler;
use QS\Core\Logging\Logger;
use QS\Core\Versioning\MigrationRunner;
use QS\Core\Wordpress\PostTypeRegistrar;
use QS\Core\Wordpress\RestRouteRegistrar;
use QS\Modules\Agents\Infrastructure\Wordpress\ReindexAdminPage;
use QS\Modules\IdentityAccess\Infrastructure\Wordpress\RoleRegistrar;
use QS\Modules\IdentityAccess\Interfaces\Hooks\RoleHooks;

final class PluginBootstrapper
{
    private ?Container $container = null;

    public function __construct(private readonly string $rootDir)
    {
    }

    public function boot(): void
    {
        try {
            $container = $this->container();
        } catch (\Throwable $exception) {
            add_action(
                'admin_notices',
                static function () use ($exception): void {
                    printf(
                        '<div class="notice notice-error"><p><strong>QS Core:</strong> fallo al inicializar: %s</p></div>',
                        esc_html($exception->getMessage())
                    );
                }
            );

            return;
        }

        $container->get(ErrorHandler::class)->register();
        $container->get(MigrationRunner::class)->runIfNeeded();
        $container->get(ModuleRegistry::class);
        $container->get(HookLoader::class)->register([
            $container->get(RoleRegistrar::class),
            $container->get(RoleHooks::class),
            $container->get(PostTypeRegistrar::class),
            $container->get(RestRouteRegistrar::class),
            $container->get(ReindexAdminPage::class),
        ]);
    }

    public function activate(): void
    {
        $container = $this->container();

        $container->get(MigrationRunner::class)->run();
        $container->get(RoleRegistrar::class)->syncRoles();
        $container->get(PostTypeRegistrar::class)->registerPostTypes();
        $container->get(Logger::class)->info('QS Core activated.');
    }

    public function deactivate(): void
    {
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }

        $this->container()->get(Logger::class)->info('QS Core deactivated.');
    }

    public function uninstall(): void
    {
        $this->container()->get(MigrationRunner::class)->cleanup();
    }

    private function container(): Container
    {
        if ($this->container === null) {
            $builder = new ContainerBuilder($this->rootDir);
            $this->container = $builder->build();
        }

        return $this->container;
    }
}

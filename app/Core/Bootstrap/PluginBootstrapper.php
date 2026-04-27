<?php

declare(strict_types=1);

namespace QS\Core\Bootstrap;

use DI\Container;
use QS\Core\Container\ContainerBuilder;
use QS\Core\Container\ServiceProvider;
use QS\Core\Contracts\ActivationHookInterface;
use QS\Core\Contracts\HookableInterface;
use QS\Core\Errors\ErrorHandler;
use QS\Core\Logging\Logger;
use QS\Core\Versioning\MigrationRunner;
use QS\Core\Wordpress\PostTypeRegistrar;
use QS\Core\Wordpress\RestRouteRegistrar;

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
                        '<div class="notice notice-error"><p><strong>Plugin:</strong> fallo al inicializar: %s</p></div>',
                        esc_html($exception->getMessage())
                    );
                }
            );

            return;
        }

        $container->get(ErrorHandler::class)->register();
        $container->get(MigrationRunner::class)->runIfNeeded();
        $container->get(ModuleRegistry::class);

        /** @var HookableInterface $postTypeRegistrar */
        $postTypeRegistrar = $container->get(PostTypeRegistrar::class);
        /** @var HookableInterface $restRouteRegistrar */
        $restRouteRegistrar = $container->get(RestRouteRegistrar::class);

        $coreHookables = [
            $postTypeRegistrar,
            $restRouteRegistrar,
        ];
        $moduleHookables = [];

        foreach (ServiceProvider::hookables() as $hookableClass) {
            /** @var HookableInterface $hookable */
            $hookable = $container->get($hookableClass);
            $moduleHookables[] = $hookable;
        }

        $container->get(HookLoader::class)->register(array_merge($coreHookables, $moduleHookables));
    }

    public function activate(): void
    {
        $container = $this->container();

        $container->get(MigrationRunner::class)->run();
        $container->get(PostTypeRegistrar::class)->registerPostTypes();

        foreach (ServiceProvider::activationHooks() as $activationHookClass) {
            /** @var ActivationHookInterface $activationHook */
            $activationHook = $container->get($activationHookClass);
            $activationHook->run();
        }

        $container->get(Logger::class)->info('Plugin activated.');
    }

    public function deactivate(): void
    {
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }

        $this->container()->get(Logger::class)->info('Plugin deactivated.');
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

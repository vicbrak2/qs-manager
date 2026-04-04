<?php

declare(strict_types=1);

namespace QS\Core\Wordpress;

use Psr\Container\ContainerInterface;
use QS\Core\Config\ConfigLoader;
use QS\Core\Contracts\HookableInterface;

final class RestRouteRegistrar implements HookableInterface
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
        private readonly ContainerInterface $container
    ) {
    }

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('rest_api_init', [$this, 'registerRoutes']);
        }
    }

    public function registerRoutes(): void
    {
        if (! function_exists('register_rest_route')) {
            return;
        }

        foreach ($this->configLoader->load('routes/rest.php') as $route) {
            if (! is_array($route)) {
                continue;
            }

            $controller = $this->container->get((string) $route['controller']);

            register_rest_route(
                (string) $route['namespace'],
                (string) $route['route'],
                [
                    'methods' => (string) $route['methods'],
                    'callback' => [$controller, (string) $route['action']],
                    'permission_callback' => $this->resolvePermissionCallback($controller, $route),
                    'args' => is_array($route['args'] ?? null) ? $route['args'] : [],
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $route
     */
    private function resolvePermissionCallback(object $controller, array $route): callable|string
    {
        $permissionCallback = $route['permission_callback'] ?? '__return_true';

        if (is_string($permissionCallback) && method_exists($controller, $permissionCallback)) {
            return [$controller, $permissionCallback];
        }

        if (is_callable($permissionCallback)) {
            return $permissionCallback;
        }

        return (string) $permissionCallback;
    }
}

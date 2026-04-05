<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Infrastructure\Wordpress;

use QS\Core\Config\ConfigLoader;
use QS\Core\Contracts\HookableInterface;

final class RoleRegistrar implements HookableInterface
{
    private ConfigLoader $configLoader;

    public function __construct(ConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('init', [$this, 'syncRoles']);
        }
    }

    public function syncRoles(): void
    {
        if (! function_exists('add_role') || ! function_exists('get_role')) {
            return;
        }

        $roles = $this->configLoader->load('capabilities/roles.php');

        foreach ($roles as $roleKey => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $label = (string) ($definition['label'] ?? $roleKey);
            $capabilities = is_array($definition['capabilities'] ?? null)
                ? $definition['capabilities']
                : [];
            $role = get_role((string) $roleKey);

            if ($role === null) {
                add_role((string) $roleKey, $label, $capabilities);
                continue;
            }

            foreach ($capabilities as $capability => $grant) {
                if ((bool) $grant) {
                    $role->add_cap((string) $capability);
                    continue;
                }

                $role->remove_cap((string) $capability);
            }
        }

        $this->syncAdministratorCapabilities($roles);
    }

    /**
     * @param array<string, mixed> $roles
     */
    private function syncAdministratorCapabilities(array $roles): void
    {
        $administrator = get_role('administrator');

        if ($administrator === null) {
            return;
        }

        foreach ($this->administratorCapabilities($roles) as $capability => $grant) {
            if ((bool) $grant) {
                $administrator->add_cap((string) $capability);
            }
        }
    }

    /**
     * @param array<string, mixed> $roles
     * @return array<string, bool>
     */
    private function administratorCapabilities(array $roles): array
    {
        $definition = $roles['qs_admin'] ?? null;

        if (! is_array($definition)) {
            return [];
        }

        $capabilities = $definition['capabilities'] ?? null;

        if (! is_array($capabilities)) {
            return [];
        }

        return array_map(
            static fn (mixed $grant): bool => (bool) $grant,
            $capabilities
        );
    }
}

<?php

declare(strict_types=1);

namespace QS\Core\Wordpress;

use QS\Core\Config\ConfigLoader;
use QS\Core\Contracts\HookableInterface;

final class RoleRegistrar implements HookableInterface
{
    public function __construct(private readonly ConfigLoader $configLoader)
    {
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

        foreach ($this->configLoader->load('capabilities/roles.php') as $roleKey => $definition) {
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
    }
}

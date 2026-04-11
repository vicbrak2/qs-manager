<?php

declare(strict_types=1);

namespace QS\Core\Security;

use QS\Core\Config\PluginConfig;

final class CapabilityChecker
{
    private string $adminOverrideCapability;

    /** @var list<string> */
    private array $adminOverridePrefixes;

    public function __construct(PluginConfig $config)
    {
        $this->adminOverrideCapability = (string) $config->get('capabilities.admin_override_capability', 'manage_options');

        $prefixes = $config->get('capabilities.admin_override_prefixes', []);
        $prefixes = is_array($prefixes) ? array_values($prefixes) : [];

        $this->adminOverridePrefixes = array_values(array_filter(
            $prefixes,
            static fn (mixed $prefix): bool => is_string($prefix) && $prefix !== ''
        ));
    }

    public function currentUserCan(string $capability): bool
    {
        if (! $this->wordpressFunctionExists('current_user_can')) {
            return false;
        }

        if ($this->allowsAdminOverride($capability) && (bool) current_user_can($this->adminOverrideCapability)) {
            return true;
        }

        return (bool) current_user_can($capability);
    }

    public function userCan(int $userId, string $capability): bool
    {
        if (! $this->wordpressFunctionExists('user_can')) {
            return false;
        }

        if ($this->allowsAdminOverride($capability) && (bool) user_can($userId, $this->adminOverrideCapability)) {
            return true;
        }

        return (bool) user_can($userId, $capability);
    }

    private function allowsAdminOverride(string $capability): bool
    {
        foreach ($this->adminOverridePrefixes as $prefix) {
            if (str_starts_with($capability, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function wordpressFunctionExists(string $function): bool
    {
        return function_exists(__NAMESPACE__ . '\\' . $function) || function_exists($function);
    }
}

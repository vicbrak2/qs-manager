<?php

declare(strict_types=1);

namespace QS\Core\Versioning;

use QS\Core\Config\PluginConfig;

final class PluginVersion
{
    public function __construct(private readonly PluginConfig $config)
    {
    }

    public function current(): string
    {
        return (string) $this->config->get('version', '1.0.0');
    }

    public function schemaVersion(): string
    {
        return (string) $this->config->get('schema_version', '0008');
    }

    public function restNamespace(): string
    {
        return (string) $this->config->get('rest.namespace', 'qs/v1');
    }

    public function versionOptionKey(): string
    {
        return $this->config->option('version');
    }

    public function installedAtOptionKey(): string
    {
        return $this->config->option('installed_at');
    }

    public function schemaOptionKey(): string
    {
        return $this->config->option('schema_version');
    }

    public function financeSettingsOptionKey(): string
    {
        return $this->config->option('finance_settings');
    }

    public function bookingSyncSettingsOptionKey(): string
    {
        return $this->config->option('booking_sync_settings');
    }
}

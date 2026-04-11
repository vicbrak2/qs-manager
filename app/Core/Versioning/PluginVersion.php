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
        return $this->optionKey('version');
    }

    public function installedAtOptionKey(): string
    {
        return $this->optionKey('installed_at');
    }

    public function schemaOptionKey(): string
    {
        return $this->optionKey('schema_version');
    }

    public function optionKey(string $key): string
    {
        return $this->config->option($key);
    }

    /**
     * @return array<string, string>
     */
    public function optionKeys(): array
    {
        $options = $this->config->get('options', []);

        if (! is_array($options)) {
            return [];
        }

        $keys = [];

        foreach ($options as $name => $value) {
            if (is_string($name) && is_string($value) && $value !== '') {
                $keys[$name] = $value;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultOptionValues(): array
    {
        $defaults = $this->config->get('option_defaults', []);

        return is_array($defaults) ? $defaults : [];
    }
}

<?php

declare(strict_types=1);

namespace QS\Core\Config;

final class ConfigLoader
{
    public function __construct(
        private readonly string $rootDir,
        private readonly EnvironmentDetector $environmentDetector
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $relativePath): array
    {
        $path = $this->rootDir . '/config/' . ltrim($relativePath, '/');

        if (! file_exists($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadEnvironmentConfig(): array
    {
        return $this->load(sprintf('environments/%s.php', $this->environmentDetector->detect()));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadModuleConfigs(): array
    {
        $files = glob($this->rootDir . '/config/modules/*.php') ?: [];
        $modules = [];

        foreach ($files as $file) {
            $config = require $file;

            if (! is_array($config) || ! isset($config['name']) || ! is_string($config['name'])) {
                continue;
            }

            $modules[$config['name']] = $config;
        }

        ksort($modules);

        return $modules;
    }
}

<?php

declare(strict_types=1);

namespace QS\Core\Versioning;

use QS\Core\Errors\QsException;
use QS\Core\Logging\Logger;

final class MigrationRunner
{
    public function __construct(
        private readonly string $rootDir,
        private readonly PluginVersion $pluginVersion,
        private readonly Logger $logger
    ) {
    }

    public function run(): void
    {
        global $wpdb;

        if (! isset($wpdb)) {
            throw new QsException('wpdb is not available during migration execution.');
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $latestSchemaVersion = '0000';

        foreach ($this->migrationFiles() as $path) {
            $migration = require $path;

            if (! is_array($migration)) {
                throw new QsException(sprintf('Migration file %s must return an array.', basename($path)));
            }

            $version = (string) ($migration['version'] ?? '');
            $callback = $migration['up'] ?? null;

            if ($version === '' || ! is_callable($callback)) {
                throw new QsException(sprintf('Migration file %s is invalid.', basename($path)));
            }

            $callback($wpdb, $charsetCollate);

            if (strcmp($version, $latestSchemaVersion) > 0) {
                $latestSchemaVersion = $version;
            }
        }

        $this->ensureDefaultOptions();
        $this->updateVersionOptions($latestSchemaVersion);

        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }

        $this->logger->info('Database migrations completed.');
    }

    public function runIfNeeded(): void
    {
        if (! function_exists('get_option')) {
            return;
        }

        $installedSchemaVersion = (string) (get_option($this->pluginVersion->schemaOptionKey(), '0000') ?: '0000');

        if (strcmp($installedSchemaVersion, $this->pluginVersion->schemaVersion()) >= 0) {
            return;
        }

        $this->run();
    }

    public function cleanup(): void
    {
        foreach ($this->pluginVersion->optionKeys() as $option) {
            if ($option !== '' && function_exists('delete_option')) {
                delete_option($option);
            }
        }

        $this->logger->info('Plugin uninstall cleanup executed.');
    }

    /**
     * @return array<int, string>
     */
    private function migrationFiles(): array
    {
        $files = glob($this->rootDir . '/database/migrations/*.php') ?: [];
        sort($files);

        return array_values($files);
    }

    private function updateVersionOptions(string $latestSchemaVersion): void
    {
        update_option(
            $this->pluginVersion->versionOptionKey(),
            $this->pluginVersion->current(),
            false
        );
        update_option(
            $this->pluginVersion->schemaOptionKey(),
            $latestSchemaVersion === '0000'
                ? $this->pluginVersion->schemaVersion()
                : $latestSchemaVersion,
            false
        );

        if (get_option($this->pluginVersion->installedAtOptionKey(), false) === false) {
            add_option($this->pluginVersion->installedAtOptionKey(), gmdate('c'), '', false);
        }
    }

    private function ensureDefaultOptions(): void
    {
        foreach ($this->pluginVersion->defaultOptionValues() as $name => $defaultValue) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $optionKey = $this->pluginVersion->optionKey($name);

            if ($optionKey === '' || get_option($optionKey, false) !== false) {
                continue;
            }

            add_option($optionKey, $defaultValue, '', false);
        }
    }
}

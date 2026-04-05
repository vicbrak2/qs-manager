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

    public function cleanup(): void
    {
        $options = [
            $this->pluginVersion->versionOptionKey(),
            $this->pluginVersion->installedAtOptionKey(),
            $this->pluginVersion->schemaOptionKey(),
            $this->pluginVersion->financeSettingsOptionKey(),
            $this->pluginVersion->bookingSyncSettingsOptionKey(),
        ];

        foreach ($options as $option) {
            if ($option !== '' && function_exists('delete_option')) {
                delete_option($option);
            }
        }

        $this->logger->info('QS Core uninstall cleanup executed.');
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
        $financeKey = $this->pluginVersion->financeSettingsOptionKey();
        $bookingKey = $this->pluginVersion->bookingSyncSettingsOptionKey();

        if ($financeKey !== '' && get_option($financeKey, false) === false) {
            add_option(
                $financeKey,
                [
                    'currency' => 'CLP',
                    'monthly_fixed_costs' => [],
                ],
                '',
                false
            );
        }

        if ($bookingKey !== '' && get_option($bookingKey, false) === false) {
            add_option(
                $bookingKey,
                [
                    'provider' => 'latepoint',
                    'enabled' => true,
                    'mode' => 'wpdb_adapter',
                ],
                '',
                false
            );
        }
    }
}

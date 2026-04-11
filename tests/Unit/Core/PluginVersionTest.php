<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Core;

use QS\Core\Config\PluginConfig;
use QS\Core\Versioning\PluginVersion;
use QS\Shared\Testing\TestCase;

final class PluginVersionTest extends TestCase
{
    public function testExposesConfiguredVersionMetadata(): void
    {
        $version = new PluginVersion(
            new PluginConfig(
                [
                    'version' => '1.2.3',
                    'schema_version' => '0042',
                    'logging' => ['file' => 'plugin.log'],
                    'rest' => ['namespace' => 'qs/v1'],
                    'capabilities' => [
                        'admin_override_capability' => 'manage_options',
                        'admin_override_prefixes' => ['qs_'],
                    ],
                    'options' => [
                        'version' => 'qs_core_version',
                        'installed_at' => 'qs_core_installed_at',
                        'schema_version' => 'qs_core_schema_version',
                        'finance_settings' => 'qs_finance_settings',
                        'booking_sync_settings' => 'qs_booking_sync_settings',
                    ],
                    'option_defaults' => [
                        'finance_settings' => ['currency' => 'CLP'],
                    ],
                ]
            )
        );

        self::assertSame('1.2.3', $version->current());
        self::assertSame('0042', $version->schemaVersion());
        self::assertSame('qs/v1', $version->restNamespace());
        self::assertSame('qs_core_version', $version->versionOptionKey());
        self::assertSame('qs_finance_settings', $version->optionKey('finance_settings'));
        self::assertSame(['currency' => 'CLP'], $version->defaultOptionValues()['finance_settings']);
    }
}

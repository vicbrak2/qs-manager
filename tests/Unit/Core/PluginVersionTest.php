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
                    'rest' => ['namespace' => 'qs/v1'],
                    'options' => [
                        'version' => 'qs_core_version',
                        'installed_at' => 'qs_core_installed_at',
                        'schema_version' => 'qs_core_schema_version',
                        'finance_settings' => 'qs_finance_settings',
                        'booking_sync_settings' => 'qs_booking_sync_settings',
                    ],
                ]
            )
        );

        self::assertSame('1.2.3', $version->current());
        self::assertSame('0042', $version->schemaVersion());
        self::assertSame('qs/v1', $version->restNamespace());
        self::assertSame('qs_core_version', $version->versionOptionKey());
    }
}

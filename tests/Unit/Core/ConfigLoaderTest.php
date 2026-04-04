<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Core;

use QS\Core\Config\ConfigLoader;
use QS\Core\Config\EnvironmentDetector;
use QS\Shared\Testing\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadReturnsPluginConfiguration(): void
    {
        $loader = new ConfigLoader(QS_CORE_ROOT_DIR, new EnvironmentDetector());
        $config = $loader->load('plugin.php');

        self::assertSame('qs-core', $config['name']);
        self::assertSame('1.0.0', $config['version']);
    }

    public function testLoadModuleConfigsReturnsNamedModules(): void
    {
        $loader = new ConfigLoader(QS_CORE_ROOT_DIR, new EnvironmentDetector());
        $modules = $loader->loadModuleConfigs();

        self::assertArrayHasKey('booking', $modules);
        self::assertArrayHasKey('bitacora', $modules);
        self::assertArrayHasKey('finance', $modules);
    }
}

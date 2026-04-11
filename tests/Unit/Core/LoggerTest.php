<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Core;

use QS\Core\Config\PluginConfig;
use QS\Core\Logging\Logger;
use QS\Shared\Testing\TestCase;

final class LoggerTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootDir = sys_get_temp_dir() . '/qs-core-logger-test-' . uniqid('', true);
        mkdir($this->rootDir . '/logs', 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (! is_dir($this->rootDir)) {
            return;
        }

        $files = glob($this->rootDir . '/logs/*') ?: [];

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        @rmdir($this->rootDir . '/logs');
        @rmdir($this->rootDir);
    }

    public function testWritesToConfiguredLogFile(): void
    {
        $logger = new Logger($this->rootDir, new PluginConfig([
            'paths' => ['logs' => 'logs'],
            'logging' => ['file' => 'plugin.log'],
        ]));

        $logger->info('mensaje de prueba');

        $logFile = $this->rootDir . '/logs/plugin.log';

        self::assertFileExists($logFile);
        self::assertStringContainsString('mensaje de prueba', (string) file_get_contents($logFile));
    }
}

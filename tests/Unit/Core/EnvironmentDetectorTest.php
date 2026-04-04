<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Core;

use QS\Core\Config\EnvironmentDetector;
use QS\Shared\Testing\TestCase;

final class EnvironmentDetectorTest extends TestCase
{
    public function testDetectReturnsConstantOrLocalDefault(): void
    {
        putenv('WP_ENVIRONMENT_TYPE');

        $detector = new EnvironmentDetector();
        $expected = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'local';

        self::assertSame($expected, $detector->detect());
    }

    public function testDetectUsesEnvironmentVariableWhenConstantIsAbsent(): void
    {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            self::markTestSkipped('WP_ENVIRONMENT_TYPE constant is already defined.');
        }

        putenv('WP_ENVIRONMENT_TYPE=production');

        try {
            $detector = new EnvironmentDetector();

            self::assertSame('production', $detector->detect());
            self::assertTrue($detector->isProduction());
        } finally {
            putenv('WP_ENVIRONMENT_TYPE');
        }
    }
}

<?php

declare(strict_types=1);

namespace QS\Tests\Integration\Core;

use QS\Core\Config\ConfigLoader;
use QS\Core\Config\EnvironmentDetector;
use QS\Core\Config\PluginConfig;
use QS\Core\Logging\Logger;
use QS\Core\Versioning\MigrationRunner;
use QS\Core\Versioning\PluginVersion;
use QS\Core\Wordpress\PostTypeRegistrar;
use QS\Core\Wordpress\RoleRegistrar;
use QS\Shared\Testing\WpTestCase;

final class MigrationRunnerTest extends WpTestCase
{
    public function testRunCreatesOptionsTablesRolesAndPostTypes(): void
    {
        $this->requireWordPressRuntime();

        global $wpdb;

        $runner = $this->runner();
        $runner->run();

        self::assertSame('1.0.0', get_option('qs_core_version'));
        self::assertSame($wpdb->prefix . 'qs_staff', $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'qs_staff')));
        self::assertNotNull(get_role('qs_admin'));
        self::assertTrue(post_type_exists('qs_bitacora'));
    }

    private function runner(): MigrationRunner
    {
        $environmentDetector = new EnvironmentDetector();
        $configLoader = new ConfigLoader(QS_CORE_ROOT_DIR, $environmentDetector);
        $pluginConfig = new PluginConfig(
            array_replace_recursive(
                $configLoader->load('plugin.php'),
                $configLoader->loadEnvironmentConfig()
            )
        );

        return new MigrationRunner(
            QS_CORE_ROOT_DIR,
            new PluginVersion($pluginConfig),
            new Logger($pluginConfig),
            new RoleRegistrar($configLoader),
            new PostTypeRegistrar()
        );
    }
}

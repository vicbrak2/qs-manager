<?php

declare(strict_types=1);

namespace QS\Tests\Contract\RestSchemas;

use QS\Core\Bootstrap\PluginBootstrapper;
use QS\Shared\Testing\WpTestCase;

final class StaffEndpointsTest extends WpTestCase
{
    public function testStaffRoutesAreRegistered(): void
    {
        $this->requireWordPressRuntime();

        if (! function_exists('rest_get_server')) {
            self::markTestSkipped('REST server is not available.');
        }

        $bootstrapper = new PluginBootstrapper(QS_CORE_ROOT_DIR);
        $bootstrapper->boot();
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();

        self::assertArrayHasKey('/qs/v1/staff', $routes);
        self::assertArrayHasKey('/qs/v1/bookings', $routes);
    }
}

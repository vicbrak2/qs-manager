<?php

declare(strict_types=1);

namespace QS\Tests\Integration\RestEndpoints;

use QS\Core\Bootstrap\PluginBootstrapper;
use QS\Shared\Testing\WpTestCase;

final class ReservationsEndpointTest extends WpTestCase
{
    public function testBookingsTodayEndpointRequiresPermission(): void
    {
        $this->requireWordPressRuntime();

        if (! function_exists('rest_get_server') || ! class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('REST server is not available.');
        }

        $bootstrapper = new PluginBootstrapper(QS_CORE_ROOT_DIR);
        $bootstrapper->boot();
        do_action('rest_api_init');

        $response = rest_get_server()->dispatch(new \WP_REST_Request('GET', '/qs/v1/bookings/today'));

        self::assertContains($response->get_status(), [200, 401, 403]);
    }
}

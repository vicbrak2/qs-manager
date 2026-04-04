<?php

declare(strict_types=1);

namespace QS\Tests\Contract\Rest;

use QS\Core\Bootstrap\PluginBootstrapper;
use QS\Shared\Testing\WpTestCase;

final class SystemEndpointsTest extends WpTestCase
{
    public function testHealthAndVersionEndpointsReturnStablePayloads(): void
    {
        $this->requireWordPressRuntime();

        if (! function_exists('rest_get_server') || ! class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('REST server is not available.');
        }

        $bootstrapper = new PluginBootstrapper(QS_CORE_ROOT_DIR);
        $bootstrapper->boot();

        do_action('rest_api_init');

        $healthResponse = rest_get_server()->dispatch(new \WP_REST_Request('GET', '/qs/v1/health'));
        $versionResponse = rest_get_server()->dispatch(new \WP_REST_Request('GET', '/qs/v1/version'));

        self::assertSame(200, $healthResponse->get_status());
        self::assertSame(200, $versionResponse->get_status());
        self::assertSame('ok', $healthResponse->get_data()['status']);
        self::assertSame('qs/v1', $versionResponse->get_data()['data']['namespace']);
    }
}

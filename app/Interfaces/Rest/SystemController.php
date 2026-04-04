<?php

declare(strict_types=1);

namespace QS\Interfaces\Rest;

use QS\Core\Config\EnvironmentDetector;
use QS\Core\Versioning\PluginVersion;
use QS\Shared\Clock\SystemClock;
use QS\Shared\DTO\RestResponse;

final class SystemController
{
    public function __construct(
        private readonly PluginVersion $pluginVersion,
        private readonly EnvironmentDetector $environmentDetector,
        private readonly SystemClock $clock
    ) {
    }

    public function health(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(
            (new RestResponse(
                'ok',
                [
                    'plugin' => 'qs-core',
                    'version' => $this->pluginVersion->current(),
                    'environment' => $this->environmentDetector->detect(),
                    'timestamp' => $this->clock->now()->format(DATE_ATOM),
                ]
            ))->toArray(),
            200
        );
    }

    public function version(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(
            (new RestResponse(
                'ok',
                [
                    'plugin' => 'qs-core',
                    'version' => $this->pluginVersion->current(),
                    'schema_version' => $this->pluginVersion->schemaVersion(),
                    'namespace' => $this->pluginVersion->restNamespace(),
                ]
            ))->toArray(),
            200
        );
    }
}

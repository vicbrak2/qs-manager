<?php

declare(strict_types=1);

namespace QS\Core\Config;

final class EnvironmentDetector
{
    public function detect(): string
    {
        if (defined('WP_ENVIRONMENT_TYPE') && is_string(WP_ENVIRONMENT_TYPE) && WP_ENVIRONMENT_TYPE !== '') {
            return WP_ENVIRONMENT_TYPE;
        }

        $environment = getenv('WP_ENVIRONMENT_TYPE');

        if (is_string($environment) && $environment !== '') {
            return $environment;
        }

        return 'local';
    }

    public function isProduction(): bool
    {
        return $this->detect() === 'production';
    }
}

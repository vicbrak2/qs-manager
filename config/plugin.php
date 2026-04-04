<?php

declare(strict_types=1);

return [
    'name' => 'qs-core',
    'display_name' => 'QS Core',
    'text_domain' => 'qs-core',
    'version' => '1.0.0',
    'schema_version' => '0006',
    'rest' => [
        'namespace' => 'qs/v1',
    ],
    'options' => [
        'version' => 'qs_core_version',
        'installed_at' => 'qs_core_installed_at',
        'schema_version' => 'qs_core_schema_version',
        'finance_settings' => 'qs_finance_settings',
        'booking_sync_settings' => 'qs_booking_sync_settings',
    ],
    'paths' => [
        'logs' => 'var/logs',
        'cache' => 'var/cache',
        'tmp' => 'var/tmp',
        'migrations' => 'database/migrations',
    ],
];

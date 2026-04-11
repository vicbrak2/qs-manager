<?php

declare(strict_types=1);

return [
    'name' => 'qs-core',
    'display_name' => 'QS Core',
    'text_domain' => 'qs-core',
    'version' => '1.0.0',
    'schema_version' => '0009',
    'logging' => [
        'file' => 'qs-core.log',
    ],
    'rest' => [
        'namespace' => 'qs/v1',
    ],
    'capabilities' => [
        'admin_override_capability' => 'manage_options',
        'admin_override_prefixes' => ['qs_'],
    ],
    'options' => [
        'version' => 'qs_core_version',
        'installed_at' => 'qs_core_installed_at',
        'schema_version' => 'qs_core_schema_version',
        'finance_settings' => 'qs_finance_settings',
        'booking_sync_settings' => 'qs_booking_sync_settings',
    ],
    'option_defaults' => [
        'finance_settings' => [
            'currency' => 'CLP',
            'monthly_fixed_costs' => [],
        ],
        'booking_sync_settings' => [
            'provider' => 'latepoint',
            'enabled' => true,
            'mode' => 'wpdb_adapter',
        ],
    ],
    'paths' => [
        'logs' => 'var/logs',
        'cache' => 'var/cache',
        'tmp' => 'var/tmp',
        'migrations' => 'database/migrations',
    ],
];

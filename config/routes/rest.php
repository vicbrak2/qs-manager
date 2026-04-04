<?php

declare(strict_types=1);

return [
    [
        'namespace' => 'qs/v1',
        'route' => '/health',
        'methods' => 'GET',
        'controller' => \QS\Interfaces\Rest\SystemController::class,
        'action' => 'health',
        'permission_callback' => '__return_true',
    ],
    [
        'namespace' => 'qs/v1',
        'route' => '/version',
        'methods' => 'GET',
        'controller' => \QS\Interfaces\Rest\SystemController::class,
        'action' => 'version',
        'permission_callback' => '__return_true',
    ],
];

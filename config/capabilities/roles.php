<?php

declare(strict_types=1);

return [
    'qs_admin' => [
        'label' => 'QS Admin',
        'capabilities' => [
            'read' => true,
            'manage_options' => true,
            'qs_manage_staff' => true,
            'qs_view_finance' => true,
            'qs_manage_bitacoras' => true,
            'qs_manage_bookings' => true,
            'qs_manage_content_qa' => true,
            'qs_manage_agents' => true,
        ],
    ],
    'qs_coordinadora' => [
        'label' => 'QS Coordinadora',
        'capabilities' => [
            'read' => true,
            'qs_manage_staff' => true,
            'qs_view_finance' => true,
            'qs_manage_bitacoras' => true,
            'qs_manage_bookings' => true,
            'qs_manage_content_qa' => false,
            'qs_manage_agents' => false,
        ],
    ],
    'qs_staff' => [
        'label' => 'QS Staff',
        'capabilities' => [
            'read' => true,
            'qs_manage_staff' => false,
            'qs_view_finance' => false,
            'qs_manage_bitacoras' => false,
            'qs_manage_bookings' => false,
            'qs_manage_content_qa' => false,
            'qs_manage_agents' => false,
        ],
    ],
];

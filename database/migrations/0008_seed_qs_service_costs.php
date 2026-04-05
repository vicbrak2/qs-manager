<?php

declare(strict_types=1);

return [
    'version' => '0008',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $costsTable = $wpdb->prefix . 'qs_service_costs';
        $serviceDefinitions = [
            [
                'name' => 'Maquillaje Social',
                'category' => 'maquillaje',
                'staff_cost_clp' => 40000,
                'staff_required' => 'mua',
            ],
            [
                'name' => 'Combo Social M+P',
                'category' => 'combo',
                'staff_cost_clp' => 60000,
                'staff_required' => 'ambos',
            ],
            [
                'name' => 'Novia Civil M+P',
                'category' => 'novia',
                'staff_cost_clp' => 60000,
                'staff_required' => 'ambos',
            ],
            [
                'name' => 'Novia Fiesta M+P',
                'category' => 'novia',
                'staff_cost_clp' => 60000,
                'staff_required' => 'ambos',
            ],
            [
                'name' => 'Graduación M+P',
                'category' => 'combo',
                'staff_cost_clp' => 60000,
                'staff_required' => 'ambos',
            ],
            [
                'name' => 'Taller Automaquillaje Individual',
                'category' => 'taller',
                'staff_cost_clp' => 40000,
                'staff_required' => 'mua',
            ],
            [
                'name' => 'Taller Automaquillaje Grupal',
                'category' => 'taller',
                'staff_cost_clp' => 40000,
                'staff_required' => 'mua',
            ],
        ];

        $serviceTables = [
            $wpdb->prefix . 'latepoint_services',
            $wpdb->prefix . 'lp_services',
        ];

        $servicesTable = null;

        foreach ($serviceTables as $candidateTable) {
            $tableExists = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $candidateTable)
            );

            if ($tableExists === $candidateTable) {
                $servicesTable = $candidateTable;
                break;
            }
        }

        if ($servicesTable === null) {
            return;
        }

        $nameColumn = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
                $servicesTable,
                'name'
            )
        ) === 'name'
            ? 'name'
            : 'title';

        foreach ($serviceDefinitions as $serviceDefinition) {
            $serviceId = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$servicesTable} WHERE {$nameColumn} = %s LIMIT 1",
                    $serviceDefinition['name']
                )
            );

            if ($serviceId <= 0) {
                continue;
            }

            $alreadySeeded = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$costsTable} WHERE lp_service_id = %d",
                    $serviceId
                )
            );

            if ($alreadySeeded > 0) {
                continue;
            }

            $wpdb->insert(
                $costsTable,
                [
                    'lp_service_id' => $serviceId,
                    'category' => $serviceDefinition['category'],
                    'staff_cost_clp' => $serviceDefinition['staff_cost_clp'],
                    'staff_required' => $serviceDefinition['staff_required'],
                    'is_active' => 1,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ],
                ['%d', '%s', '%d', '%s', '%d', '%s', '%s']
            );
        }
    },
];

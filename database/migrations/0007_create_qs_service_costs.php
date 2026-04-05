<?php

declare(strict_types=1);

return [
    'version' => '0007',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_service_costs';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                lp_service_id bigint(20) unsigned NOT NULL,
                category varchar(50) NOT NULL,
                staff_cost_clp bigint(20) unsigned NOT NULL DEFAULT 0,
                staff_required varchar(20) NOT NULL,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                description text DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY lp_service_id (lp_service_id),
                KEY category (category),
                KEY staff_required (staff_required),
                KEY is_active (is_active)
            ) {$charsetCollate};"
        );
    },
];

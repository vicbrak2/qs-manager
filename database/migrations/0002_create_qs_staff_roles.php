<?php

declare(strict_types=1);

return [
    'version' => '0002',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_staff_roles';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                staff_id bigint(20) unsigned NOT NULL,
                rol varchar(50) NOT NULL,
                asignado_desde date NOT NULL,
                asignado_hasta date DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY staff_id (staff_id),
                KEY rol (rol)
            ) {$charsetCollate};"
        );
    },
];

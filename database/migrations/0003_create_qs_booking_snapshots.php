<?php

declare(strict_types=1);

return [
    'version' => '0003',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_booking_snapshots';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                latepoint_booking_id bigint(20) unsigned NOT NULL,
                fecha_servicio date NOT NULL,
                hora_inicio time NOT NULL,
                hora_fin time NOT NULL,
                servicio_nombre varchar(150) NOT NULL,
                staff_id bigint(20) unsigned DEFAULT NULL,
                clienta_nombre varchar(150) NOT NULL,
                clienta_email varchar(190) DEFAULT NULL,
                clienta_telefono varchar(50) DEFAULT NULL,
                estado varchar(30) NOT NULL,
                precio_clp bigint(20) unsigned DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY latepoint_booking_id (latepoint_booking_id),
                KEY fecha_servicio (fecha_servicio),
                KEY staff_id (staff_id),
                KEY estado (estado)
            ) {$charsetCollate};"
        );
    },
];

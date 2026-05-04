<?php

declare(strict_types=1);

return [
    'version' => '0012',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_sheet_events';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                sheet_name varchar(50) NOT NULL COMMENT 'Pestaña del mes: Enero, Febrero, etc.',
                row_index int(11) unsigned NOT NULL COMMENT 'Índice de fila en el Sheet (1-based, sin cabecera)',
                encargada varchar(150) NOT NULL DEFAULT '',
                dia varchar(20) NOT NULL DEFAULT '',
                fecha_servicio date DEFAULT NULL,
                hora_inicio time DEFAULT NULL,
                servicio varchar(255) NOT NULL DEFAULT '',
                cantidad int(11) unsigned NOT NULL DEFAULT 1,
                clienta_nombre varchar(255) NOT NULL DEFAULT '',
                telefono varchar(50) DEFAULT NULL,
                direccion varchar(255) DEFAULT NULL,
                comuna varchar(100) DEFAULT NULL,
                traslado varchar(20) NOT NULL DEFAULT 'No',
                abono_clp bigint(20) unsigned NOT NULL DEFAULT 0,
                fecha_abono date DEFAULT NULL,
                valor_servicio_clp bigint(20) unsigned NOT NULL DEFAULT 0,
                total_servicio_clp bigint(20) unsigned NOT NULL DEFAULT 0,
                total_por_pagar_clp bigint(20) NOT NULL DEFAULT 0,
                accion varchar(100) NOT NULL DEFAULT '',
                estado_evento varchar(50) NOT NULL DEFAULT 'Pendiente',
                id_evento_gcal varchar(255) DEFAULT NULL,
                origen varchar(20) NOT NULL DEFAULT 'sheet' COMMENT 'sheet | form',
                synced_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY sheet_row (sheet_name, row_index),
                KEY fecha_servicio (fecha_servicio),
                KEY encargada (encargada(50)),
                KEY estado_evento (estado_evento),
                KEY origen (origen),
                KEY clienta_nombre (clienta_nombre(50))
            ) {$charsetCollate};"
        );
    },
];

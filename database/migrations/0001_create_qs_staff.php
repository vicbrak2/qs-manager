<?php

declare(strict_types=1);

return [
    'version' => '0001',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_staff';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                nombre varchar(100) NOT NULL,
                apellido varchar(100) NOT NULL,
                especialidad varchar(50) NOT NULL,
                costo_hora_clp bigint(20) unsigned NOT NULL DEFAULT 0,
                contacto_whatsapp varchar(50) DEFAULT NULL,
                estado varchar(20) NOT NULL DEFAULT 'activo',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY especialidad (especialidad),
                KEY estado (estado)
            ) {$charsetCollate};"
        );
    },
];

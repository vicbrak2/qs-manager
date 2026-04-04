<?php

declare(strict_types=1);

return [
    'version' => '0006',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_audit_log';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                usuario_id bigint(20) unsigned DEFAULT NULL,
                accion varchar(100) NOT NULL,
                modulo varchar(100) NOT NULL,
                entidad_tipo varchar(100) NOT NULL,
                entidad_id bigint(20) unsigned DEFAULT NULL,
                datos_anteriores longtext DEFAULT NULL,
                datos_nuevos longtext DEFAULT NULL,
                ip varchar(45) DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY usuario_id (usuario_id),
                KEY modulo (modulo),
                KEY entidad_tipo_id (entidad_tipo, entidad_id)
            ) {$charsetCollate};"
        );
    },
];

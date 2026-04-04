<?php

declare(strict_types=1);

return [
    'version' => '0004',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_finance_entries';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                tipo varchar(20) NOT NULL,
                concepto varchar(190) NOT NULL,
                monto_clp bigint(20) NOT NULL,
                metodo_pago varchar(50) DEFAULT NULL,
                servicio_id bigint(20) unsigned DEFAULT NULL,
                staff_id bigint(20) unsigned DEFAULT NULL,
                fecha date NOT NULL,
                mes_anio char(7) NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY tipo (tipo),
                KEY fecha (fecha),
                KEY mes_anio (mes_anio)
            ) {$charsetCollate};"
        );
    },
];

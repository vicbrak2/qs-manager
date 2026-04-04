<?php

declare(strict_types=1);

return [
    'version' => '0005',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_leads_timeline';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint(20) unsigned NOT NULL,
                accion varchar(100) NOT NULL,
                detalle text DEFAULT NULL,
                usuario_id bigint(20) unsigned DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY lead_id (lead_id),
                KEY usuario_id (usuario_id)
            ) {$charsetCollate};"
        );
    },
];

<?php

declare(strict_types=1);

return [
    'version' => '0011',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_bookings';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                client_name varchar(255) NOT NULL,
                client_phone varchar(50) DEFAULT NULL,
                client_email varchar(255) DEFAULT NULL,
                service_name varchar(255) NOT NULL,
                start_time datetime NOT NULL,
                end_time datetime NOT NULL,
                status varchar(50) NOT NULL,
                google_event_id varchar(255) DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY start_time (start_time),
                KEY status (status)
            ) {$charsetCollate};"
        );
    },
];

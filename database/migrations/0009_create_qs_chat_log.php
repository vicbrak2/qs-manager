<?php

declare(strict_types=1);

return [
    'version' => '0009',
    'up' => static function (\wpdb $wpdb, string $charsetCollate): void {
        $table = $wpdb->prefix . 'qs_chat_log';

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                session_id varchar(191) NOT NULL,
                turn_index int(10) unsigned NOT NULL,
                user_message longtext NOT NULL,
                bot_response longtext NOT NULL,
                feedback_rating varchar(16) DEFAULT NULL,
                is_fallback tinyint(1) NOT NULL DEFAULT 0,
                fallback_reason varchar(64) DEFAULT NULL,
                feedback_updated_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY session_turn (session_id, turn_index),
                KEY feedback_rating (feedback_rating),
                KEY created_at (created_at)
            ) {$charsetCollate};"
        );
    },
];

<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Database;

use PDO;

final class ConnectionFactory
{
    public static function fromEnvironment(): PDO
    {
        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '5432');
        $database = self::env('DB_NAME', 'qs_manager_v2');
        $user = self::env('DB_USER', 'qs_user');
        $password = self::env('DB_PASSWORD', 'qs_password');

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database);

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}


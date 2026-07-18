<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dbName = getenv('DB_NAME') ?: '';

if (!str_ends_with($dbName, '_test')) {
    throw new RuntimeException(
        'PHPUnit refuses to run against a non-test database.'
    );
}

// Ensure the schema is up to date before running tests
require __DIR__ . '/../tools/migrate.php';
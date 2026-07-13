<?php

declare(strict_types=1);

use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;

require dirname(__DIR__) . '/vendor/autoload.php';

$enabled = getenv('SHEETS_READ_SYNC_ENABLED');
if (!in_array(strtolower((string) $enabled), ['1', 'true', 'yes', 'on'], true)) {
    fwrite(STDERR, "Sheets read sync is disabled. Set SHEETS_READ_SYNC_ENABLED=true.\n");
    exit(2);
}

$importer = new PostgresSheetReplicaImporter(
    ConnectionFactory::fromEnvironment(),
    new GoogleSheetsCsvReader(),
);

echo json_encode($importer->importAll()->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;

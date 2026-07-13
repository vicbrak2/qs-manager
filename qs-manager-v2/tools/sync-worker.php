<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use QSManager\Application\Sheets\ProcessSheetSyncRun;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Http\CurlHttpClient;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;
use QSManager\Infrastructure\Sheets\PostgresSyncRunRepository;

$pdo = ConnectionFactory::fromEnvironment();
$importer = new PostgresSheetReplicaImporter(
    $pdo,
    new GoogleSheetsCsvReader(new CurlHttpClient())
);
$repository = new PostgresSyncRunRepository($pdo);
$workerId = 'worker-' . getmypid() . '-' . substr(md5(uniqid()), 0, 8);

$processor = new ProcessSheetSyncRun($importer, $repository, $workerId);

echo "Starting Sync Worker [$workerId]...\n";

while (true) {
    try {
        $processed = $processor->processNext();
        if (!$processed) {
            sleep(2);
        }
    } catch (\Throwable $e) {
        echo "Critical worker error: " . $e->getMessage() . "\n";
        sleep(5);
    }
}

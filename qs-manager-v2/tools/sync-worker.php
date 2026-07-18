<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use QSManager\Application\Sheets\ProcessSheetSyncRun;
use QSManager\Application\Finance\RebuildFinanceProjection;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Http\CurlHttpClient;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;
use QSManager\Infrastructure\Sheets\PostgresSyncRunRepository;

function emit_log(string $level, string $message, array $context = []): void {
    echo json_encode([
        'time' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

function waitForDatabase(): PDO {
    while (true) {
        try {
            $pdo = ConnectionFactory::fromEnvironment();
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (\Throwable $e) {
            emit_log('warning', 'Database not ready, retrying in 5 seconds...', ['error' => $e->getMessage()]);
            sleep(5);
        }
    }
}

$pdo = waitForDatabase();
$importer = new PostgresSheetReplicaImporter(
    $pdo,
    new GoogleSheetsCsvReader(new CurlHttpClient())
);
$repository = new PostgresSyncRunRepository($pdo);
$workerId = 'worker-' . getmypid() . '-' . substr(md5(uniqid()), 0, 8);

$financeProjection = new RebuildFinanceProjection($pdo);
$processor = new ProcessSheetSyncRun($importer, $repository, $financeProjection, $workerId);

emit_log('info', 'Starting Sync Worker', ['worker_id' => $workerId]);

$healthFile = '/tmp/qs-sync-worker.heartbeat';

while (true) {
    try {
        touch($healthFile);
        $processed = $processor->processNext();
        touch($healthFile);
        if ($processed) {
            emit_log('info', 'Processed a sync run', ['worker_id' => $workerId]);
        } else {
            sleep(2);
        }
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (\Throwable $rollbackError) {
                emit_log('critical', 'Worker rollback failed', [
                    'worker_id' => $workerId,
                    'error' => $rollbackError->getMessage()
                ]);
                exit(1);
            }
        }
        emit_log('error', 'Critical worker error', [
            'worker_id' => $workerId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        sleep(5);
    }
}

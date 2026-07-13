<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;

$pdo = ConnectionFactory::fromEnvironment();
$importer = new PostgresSheetReplicaImporter(
    $pdo,
    new GoogleSheetsCsvReader(),
);

echo "Starting Sync Worker...\n";

while (true) {
    try {
        $pdo->beginTransaction();
        
        $statement = $pdo->query(
            "SELECT id FROM qs_sync_runs WHERE status = 'queued' ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED"
        );
        $runId = $statement->fetchColumn();
        
        if ($runId === false) {
            $pdo->commit();
            sleep(2);
            continue;
        }
        
        echo "Found pending run: $runId. Starting...\n";
        
        $pdo->prepare("UPDATE qs_sync_runs SET status = 'running', started_at = now() WHERE id = :id")
            ->execute(['id' => $runId]);
            
        $pdo->commit();
        
        try {
            $result = $importer->importAll((int) $runId);
            
            $totalSources = count($result->toArray());
            $completedSources = 0;
            $failedSources = 0;
            $totalRowsSeen = 0;
            $totalRowsImported = 0;
            $errorSummary = [];
            
            foreach ($result->toArray() as $sheet => $data) {
                if ($data['status'] === 'failed') {
                    $failedSources++;
                    $errorSummary[] = $sheet . ': ' . $data['message'];
                } else {
                    $completedSources++;
                }
                $totalRowsSeen += $data['rows_seen'];
                $totalRowsImported += $data['rows_imported'];
            }
            
            $status = $failedSources === 0 ? 'completed' : ($completedSources === 0 ? 'failed' : 'partial');
            
            $pdo->prepare("
                UPDATE qs_sync_runs 
                SET status = :status, 
                    finished_at = now(),
                    total_sources = :total_sources,
                    completed_sources = :completed,
                    failed_sources = :failed,
                    total_rows_seen = :rows_seen,
                    total_rows_imported = :rows_imported,
                    error_summary = :error_summary
                WHERE id = :id
            ")->execute([
                'status' => $status,
                'total_sources' => $totalSources,
                'completed' => $completedSources,
                'failed' => $failedSources,
                'rows_seen' => $totalRowsSeen,
                'rows_imported' => $totalRowsImported,
                'error_summary' => $errorSummary !== [] ? implode(\"\n\", $errorSummary) : null,
                'id' => $runId
            ]);
            
            echo "Run $runId finished with status $status.\n";
            
        } catch (\Throwable $e) {
            $pdo->prepare("UPDATE qs_sync_runs SET status = 'failed', finished_at = now(), error_summary = :error WHERE id = :id")
                ->execute(['error' => $e->getMessage(), 'id' => $runId]);
            echo "Run $runId failed critically: " . $e->getMessage() . "\n";
        }
        
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Worker error: " . $e->getMessage() . "\n";
        sleep(5);
    }
}

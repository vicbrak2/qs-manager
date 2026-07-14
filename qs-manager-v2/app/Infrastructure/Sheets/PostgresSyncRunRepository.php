<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use PDO;

use QSManager\Application\Sheets\SyncRunRepository;

final class PostgresSyncRunRepository implements SyncRunRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function claimNextRun(string $workerId, int $leaseTimeoutMinutes = 10): ?int
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM qs_sync_runs 
                WHERE status = 'queued' 
                   OR (status = 'running' AND heartbeat_at < now() - CAST(:timeout || ' minutes' AS INTERVAL))
                ORDER BY created_at ASC 
                LIMIT 1 
                FOR UPDATE SKIP LOCKED
            ");
            $stmt->execute(['timeout' => $leaseTimeoutMinutes]);
            $runId = $stmt->fetchColumn();

            if ($runId === false) {
                $this->pdo->commit();
                return null;
            }

            $update = $this->pdo->prepare("
                UPDATE qs_sync_runs 
                SET status = 'running',
                    worker_id = :worker_id,
                    started_at = COALESCE(started_at, now()),
                    heartbeat_at = now(),
                    attempt_count = attempt_count + 1
                WHERE id = :id
            ");
            $update->execute([
                'worker_id' => $workerId,
                'id' => $runId
            ]);

            $this->pdo->commit();
            return (int) $runId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function heartbeat(int $runId): void
    {
        $this->pdo->prepare("UPDATE qs_sync_runs SET heartbeat_at = now() WHERE id = :id")
            ->execute(['id' => $runId]);
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function markCompleted(int $runId, array $stats): void
    {
        $this->pdo->prepare("
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
            'status' => $stats['status'],
            'total_sources' => $stats['totalSources'],
            'completed' => $stats['completedSources'],
            'failed' => $stats['failedSources'],
            'rows_seen' => $stats['totalRowsSeen'],
            'rows_imported' => $stats['totalRowsImported'],
            'error_summary' => $stats['errorSummary'] ?? null,
            'id' => $runId
        ]);
    }

    public function markFailed(int $runId, string $error): void
    {
        $this->pdo->prepare("
            UPDATE qs_sync_runs 
            SET status = 'failed', 
                finished_at = now(), 
                error_summary = :error 
            WHERE id = :id
        ")->execute(['error' => $error, 'id' => $runId]);
    }
}

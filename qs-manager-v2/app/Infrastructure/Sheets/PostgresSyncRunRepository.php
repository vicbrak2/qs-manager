<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use PDO;

use QSManager\Application\Sheets\SyncRunRepository;
use QSManager\Application\Sheets\SyncRunSummary;

final class PostgresSyncRunRepository implements SyncRunRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function claimNextRun(string $workerId, int $leaseTimeoutMinutes = 10, int $maxAttempts = 3): ?int
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }

        try {
            // Reap exhausted runs
            $reapStmt = $this->pdo->prepare("
                UPDATE qs_sync_runs
                SET status = 'failed',
                    finished_at = now(),
                    error_summary = 'Maximum retry attempts exceeded.'
                WHERE attempt_count >= :max_attempts
                  AND status IN ('queued', 'running')
            ");
            $reapStmt->execute(['max_attempts' => $maxAttempts]);

            $stmt = $this->pdo->prepare("
                SELECT id FROM qs_sync_runs
                WHERE attempt_count < :max_attempts
                  AND (
                      status = 'queued'
                      OR (status = 'running' AND heartbeat_at < now() - CAST(:timeout || ' minutes' AS INTERVAL))
                  )
                ORDER BY created_at ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
            ");
            $stmt->execute([
                'max_attempts' => $maxAttempts,
                'timeout' => $leaseTimeoutMinutes
            ]);
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

    public function markCompleted(int $runId, SyncRunSummary $summary): void
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
            'status' => $summary->status,
            'total_sources' => $summary->totalSources,
            'completed' => $summary->completedSources,
            'failed' => $summary->failedSources,
            'rows_seen' => $summary->totalRowsSeen,
            'rows_imported' => $summary->totalRowsImported,
            'error_summary' => $summary->errorSummary,
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

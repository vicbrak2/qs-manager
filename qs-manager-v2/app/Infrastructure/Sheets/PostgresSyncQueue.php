<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use PDO;
use QSManager\Application\Sheets\SyncQueue;
use QSManager\Application\Sheets\EnqueueResult;

final class PostgresSyncQueue implements SyncQueue
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function enqueueOrReuse(string $triggeredBy): EnqueueResult
    {
        $this->connection->beginTransaction();

        try {
            // Deduplication lock
            $this->connection->query("SELECT pg_advisory_xact_lock(1122334455)");

            $statement = $this->connection->query(
                "SELECT id, status FROM qs_sync_runs WHERE status IN ('queued', 'running') ORDER BY id ASC LIMIT 1"
            );
            $existing = $statement->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $this->connection->commit();
                return new EnqueueResult((int) $existing['id'], $existing['status'], true);
            }

            $statement = $this->connection->prepare(
                "INSERT INTO qs_sync_runs (status, mode, triggered_by) VALUES ('queued', 'read_only', :triggered_by) RETURNING id"
            );
            $statement->execute(['triggered_by' => $triggeredBy]);
            $runId = (int) $statement->fetchColumn();

            $this->connection->commit();

            return new EnqueueResult($runId, 'queued', false);
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}

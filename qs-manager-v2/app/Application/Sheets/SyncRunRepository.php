<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

interface SyncRunRepository
{
    public function claimNextRun(string $workerId, int $leaseTimeoutMinutes = 10): ?int;
    public function heartbeat(int $runId): void;
    /** @param array<string, mixed> $stats */
    public function markCompleted(int $runId, array $stats): void;
    public function markFailed(int $runId, string $message): void;
}

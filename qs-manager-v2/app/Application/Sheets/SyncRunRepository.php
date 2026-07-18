<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

interface SyncRunRepository
{
    public function claimNextRun(string $workerId, int $leaseTimeoutMinutes = 10, int $maxAttempts = 3): ?int;
    public function heartbeat(int $runId): void;
    public function markCompleted(int $runId, SyncRunSummary $summary): void;
    public function markFailed(int $runId, string $message): void;
}

<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

final readonly class SyncRunSummary
{
    public function __construct(
        public string $status,
        public int $totalSources,
        public int $completedSources,
        public int $failedSources,
        public int $totalRowsSeen,
        public int $totalRowsImported,
        public ?string $errorSummary,
    ) {
    }
}
<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

final readonly class EnqueueResult
{
    public function __construct(
        public int $runId,
        public string $status,
        public bool $reused,
    ) {
    }
}

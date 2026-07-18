<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

interface SyncQueue
{
    /**
     * @throws \Throwable If database is unavailable
     */
    public function enqueueOrReuse(string $triggeredBy): EnqueueResult;
}

<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

interface SheetReplicaImporter
{
    public function importAll(): SheetSyncResult;
}

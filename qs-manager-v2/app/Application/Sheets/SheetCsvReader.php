<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

interface SheetCsvReader
{
    /**
     * @return list<list<string>>
     */
    public function read(string $spreadsheetId, int $gid, string $sheetName = 'Unknown'): array;
}

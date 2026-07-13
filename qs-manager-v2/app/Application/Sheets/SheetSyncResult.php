<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

final class SheetSyncResult
{
    /**
     * @param array<string, array{rows_seen:int, rows_imported:int, status:string, message:?string}> $sources
     */
    public function __construct(private readonly array $sources)
    {
    }

    public function toArray(): array
    {
        return [
            'mode' => 'read_only',
            'writes_to_sheets' => false,
            'sources' => $this->sources,
        ];
    }
}

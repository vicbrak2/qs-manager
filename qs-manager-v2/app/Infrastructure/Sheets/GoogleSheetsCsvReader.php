<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

final class GoogleSheetsCsvReader
{
    /**
     * @return list<list<string>>
     */
    public function read(string $spreadsheetId, int $gid): array
    {
        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%d',
            rawurlencode($spreadsheetId),
            $gid
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'ignore_errors' => true,
                'header' => "Accept: text/csv\r\n",
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);
        if ($contents === false || trim($contents) === '') {
            throw new \RuntimeException('Could not read Google Sheet CSV export.');
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Could not allocate CSV buffer.');
        }

        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn (?string $value): string => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use QSManager\Application\Sheets\SheetCsvReader;

final class GoogleSheetsCsvReader implements SheetCsvReader
{
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_MS = [500, 1000, 2000];

    /**
     * @return list<list<string>>
     */
    public function read(string $spreadsheetId, int $gid, string $sheetName = 'Unknown'): array
    {
        $urls = [
            sprintf('https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%d', rawurlencode($spreadsheetId), $gid),
            sprintf('https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&gid=%d', rawurlencode($spreadsheetId), $gid),
        ];

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $url = $urls[($attempt - 1) % count($urls)];
            
            try {
                return $this->tryRead($url, $spreadsheetId, $gid, $sheetName, $attempt);
            } catch (\Throwable $e) {
                $lastException = $e;
                
                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::BACKOFF_MS[$attempt - 1] * 1000);
                }
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Failed to read sheet "%s" (GID: %d) after %d attempts. Last error: %s',
                $sheetName,
                $gid,
                self::MAX_ATTEMPTS,
                $lastException ? $lastException->getMessage() : 'Unknown error'
            ),
            0,
            $lastException
        );
    }

    /**
     * @return list<list<string>>
     */
    private function tryRead(string $url, string $spreadsheetId, int $gid, string $sheetName, int $attempt): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds to connect
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);       // 15 seconds max to read
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/csv']);
        
        $contents = curl_exec($ch);
        
        if ($contents === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException(sprintf('cURL error on attempt %d: %s', $attempt, $error));
        }
        
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        curl_close($ch);
        
        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf('HTTP %d on attempt %d.', $statusCode, $attempt));
        }
        
        if (stripos($contentType, 'text/html') !== false || stripos($contents, '<html') !== false) {
            throw new \RuntimeException(sprintf('Received HTML instead of CSV on attempt %d (possible auth/login redirect).', $attempt));
        }

        if (trim((string) $contents) === '') {
            throw new \RuntimeException(sprintf('Received empty CSV export on attempt %d.', $attempt));
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


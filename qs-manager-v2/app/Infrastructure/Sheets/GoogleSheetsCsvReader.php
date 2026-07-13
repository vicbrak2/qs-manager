<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use QSManager\Application\Sheets\SheetCsvReader;
use QSManager\Infrastructure\Http\HttpClient;
use QSManager\Infrastructure\Http\CurlHttpClient;

final class GoogleSheetsCsvReader implements SheetCsvReader
{
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_MS = [500, 1000, 2000];
    
    private HttpClient $httpClient;

    public function __construct(?HttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new CurlHttpClient();
    }

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
        $response = $this->httpClient->get($url, [
            'connect_timeout' => 5,
            'timeout' => 15,
            'headers' => ['Accept: text/csv'],
        ]);
        
        if ($response['contents'] === false) {
            throw new \RuntimeException(sprintf('HTTP client error on attempt %d: %s', $attempt, $response['error'] ?? 'Unknown error'));
        }
        
        $statusCode = $response['statusCode'];
        $contentType = $response['contentType'];
        $contents = $response['contents'];
        
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


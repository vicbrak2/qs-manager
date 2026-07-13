<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Sheets;

use PHPUnit\Framework\TestCase;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;

final class GoogleSheetsCsvReaderTest extends TestCase
{
    public function testHandlesConnectionTimeoutAndRetries(): void
    {
        $reader = new GoogleSheetsCsvReader();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read sheet "Unknown" (GID: 0) after 3 attempts.');
        
        // Use an invalid hostname to force a curl error (CURLE_COULDNT_RESOLVE_HOST = 6)
        $reader->read('invalid-host-that-does-not-exist.test', 0);
    }
}

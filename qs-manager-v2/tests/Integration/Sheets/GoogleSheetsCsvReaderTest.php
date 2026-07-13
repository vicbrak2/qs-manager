<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Sheets;

use PHPUnit\Framework\TestCase;
use QSManager\Infrastructure\Http\HttpClient;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;

final class GoogleSheetsCsvReaderTest extends TestCase
{
    public function testHandlesConnectionTimeoutAndRetries(): void
    {
        $mockClient = $this->createMock(HttpClient::class);
        $mockClient->expects($this->exactly(3))
            ->method('get')
            ->willReturn([
                'statusCode' => 0,
                'contentType' => '',
                'contents' => false,
                'error' => 'cURL error 6: Could not resolve host',
            ]);

        $reader = new GoogleSheetsCsvReader($mockClient);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to read sheet "Unknown" \(GID: 0\) after 3 attempts\./');
        
        $reader->read('test-id', 0);
    }

    public function testFailsOnNon200HttpResponse(): void
    {
        $mockClient = $this->createMock(HttpClient::class);
        $mockClient->expects($this->exactly(3))
            ->method('get')
            ->willReturn([
                'statusCode' => 403,
                'contentType' => 'text/html',
                'contents' => 'Forbidden',
            ]);

        $reader = new GoogleSheetsCsvReader($mockClient);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 403 on attempt 3/');
        
        $reader->read('test-id', 0);
    }

    public function testFailsOnHtmlResponse(): void
    {
        $mockClient = $this->createMock(HttpClient::class);
        $mockClient->expects($this->exactly(3))
            ->method('get')
            ->willReturn([
                'statusCode' => 200,
                'contentType' => 'text/html',
                'contents' => '<html><body>Login redirect</body></html>',
            ]);

        $reader = new GoogleSheetsCsvReader($mockClient);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Received HTML instead of CSV on attempt 3/');
        
        $reader->read('test-id', 0);
    }

    public function testSucceedsReturningCsvRows(): void
    {
        $mockClient = $this->createMock(HttpClient::class);
        $mockClient->expects($this->once())
            ->method('get')
            ->willReturn([
                'statusCode' => 200,
                'contentType' => 'text/csv',
                'contents' => "id,name,value\n1,Test,1000\n2,\"Quoted, Name\",2000",
            ]);

        $reader = new GoogleSheetsCsvReader($mockClient);
        $rows = $reader->read('test-id', 0);

        $this->assertCount(3, $rows);
        $this->assertEquals(['id', 'name', 'value'], $rows[0]);
        $this->assertEquals(['1', 'Test', '1000'], $rows[1]);
        $this->assertEquals(['2', 'Quoted, Name', '2000'], $rows[2]);
    }
}

<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Sheets;

use PDO;
use PHPUnit\Framework\TestCase;
use QSManager\Application\Sheets\ProcessSheetSyncRun;
use QSManager\Application\Sheets\SheetReplicaImporter;
use QSManager\Application\Sheets\SheetSyncResult;
use QSManager\Application\Finance\RebuildFinanceProjection;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Sheets\PostgresSyncRunRepository;

final class SyncWorkerIntegrationTest extends TestCase
{
    private PDO $pdo;
    private PostgresSyncRunRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = ConnectionFactory::fromEnvironment();
        $this->pdo->exec('TRUNCATE qs_sync_runs RESTART IDENTITY CASCADE');
        $this->repository = new PostgresSyncRunRepository($this->pdo);
    }

    public function testReclaimsQueuedRunAndCompletesSuccessfully(): void
    {
        $this->pdo->exec("INSERT INTO qs_sync_runs (status, mode, triggered_by) VALUES ('queued', 'read_only', 'api')");

        $importer = $this->createMock(SheetReplicaImporter::class);
        $importer->expects($this->once())
            ->method('importAll')
            ->willReturnCallback(function (int $runId, callable $onSourceCompleted) {
                $onSourceCompleted(); // simulate heartbeat
                return new SheetSyncResult([
                    'source1' => ['status' => 'completed', 'rows_seen' => 10, 'rows_imported' => 10, 'message' => null],
                ]);
            });

        $finance = new RebuildFinanceProjection($this->pdo);
        $processor = new ProcessSheetSyncRun($importer, $this->repository, $finance, 'test-worker-1');
        
        $this->assertTrue($processor->processNext());

        $run = $this->pdo->query('SELECT * FROM qs_sync_runs WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('completed', $run['status']);
        $this->assertEquals('test-worker-1', $run['worker_id']);
        $this->assertEquals(1, $run['attempt_count']);
        $this->assertNotNull($run['heartbeat_at']);
        $this->assertEquals(10, $run['total_rows_imported']);
    }

    public function testDoesNotReclaimActiveRuns(): void
    {
        $this->pdo->exec("INSERT INTO qs_sync_runs (status, mode, heartbeat_at) VALUES ('running', 'read_only', now())");

        $importer = $this->createMock(SheetReplicaImporter::class);
        $finance = new RebuildFinanceProjection($this->pdo);
        $processor = new ProcessSheetSyncRun($importer, $this->repository, $finance, 'test-worker-1');
        
        $this->assertFalse($processor->processNext());
    }

    public function testReclaimsAbandonedRuns(): void
    {
        $this->pdo->exec("INSERT INTO qs_sync_runs (status, mode, heartbeat_at, worker_id, attempt_count) VALUES ('running', 'read_only', now() - interval '15 minutes', 'old-worker', 1)");

        $importer = $this->createMock(SheetReplicaImporter::class);
        $importer->expects($this->once())->method('importAll')->willReturn(new SheetSyncResult([]));

        $finance = new RebuildFinanceProjection($this->pdo);
        $processor = new ProcessSheetSyncRun($importer, $this->repository, $finance, 'test-worker-2');
        $this->assertTrue($processor->processNext());

        $run = $this->pdo->query('SELECT * FROM qs_sync_runs WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('test-worker-2', $run['worker_id']);
        $this->assertEquals(2, $run['attempt_count']); // Incremented attempts
    }

    public function testMarksRunAsFailedOnCriticalException(): void
    {
        $this->pdo->exec("INSERT INTO qs_sync_runs (status, mode) VALUES ('queued', 'read_only')");

        $importer = $this->createMock(SheetReplicaImporter::class);
        $importer->expects($this->once())
            ->method('importAll')
            ->willThrowException(new \RuntimeException('Critical database error'));

        $finance = new RebuildFinanceProjection($this->pdo);
        $processor = new ProcessSheetSyncRun($importer, $this->repository, $finance, 'test-worker-1');
        $processor->processNext();

        $run = $this->pdo->query('SELECT * FROM qs_sync_runs WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('failed', $run['status']);
        $this->assertStringContainsString('Critical database error', $run['error_summary']);
    }
}

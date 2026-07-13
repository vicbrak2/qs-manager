<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Application\Sheets\SheetReplicaImporter;
use Slim\App;

final class SheetSyncController
{
    public function __construct(
        private readonly SheetReplicaImporter $importer,
        private readonly PDO $connection,
        private readonly bool $enabled,
    ) {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/sync/sheets/status', [$this, 'status']);
        $app->post('/api/v1/sync/sheets/import', [$this, 'import']);
        $app->get('/api/v1/sync/sheets/runs/{id}', [$this, 'getRun']);
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $statement = $this->connection->query(
            'select s.sheet_name,
                    s.purpose,
                    s.last_synced_at,
                    r.status as last_run_status,
                    r.rows_seen,
                    r.rows_imported,
                    r.finished_at
             from qs_sheet_sources s
             left join lateral (
                select status, rows_seen, rows_imported, finished_at
                from qs_sheet_import_runs
                where source_id = s.id
                order by started_at desc
                limit 1
             ) r on true
             order by s.sheet_name asc'
        );

        return $this->json($response, [
            'enabled' => $this->enabled,
            'mode' => 'read_only',
            'writes_to_sheets' => false,
            'sources' => $statement->fetchAll(),
        ]);
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->json($response, [
                'error' => 'Sheets read sync is disabled.',
                'hint' => 'Set SHEETS_READ_SYNC_ENABLED=true to import from Sheets into the local database.',
                'writes_to_sheets' => false,
            ], 202);
        }

        $this->connection->beginTransaction();
        
        $this->connection->query("SELECT pg_advisory_xact_lock(1122334455)");
        
        $statement = $this->connection->query(
            "SELECT id, status FROM qs_sync_runs WHERE status IN ('queued', 'running') ORDER BY id ASC LIMIT 1"
        );
        $existing = $statement->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->connection->commit();
            return $this->json($response, [
                'run_id' => $existing['id'],
                'status' => $existing['status'],
                'message' => 'Sync already in progress or queued.',
                'reused' => true,
            ], 200);
        }

        $statement = $this->connection->prepare(
            "INSERT INTO qs_sync_runs (status, mode, triggered_by) VALUES ('queued', 'read_only', :triggered_by) RETURNING id"
        );
        $statement->execute(['triggered_by' => 'api']);
        $runId = $statement->fetchColumn();

        $this->connection->commit();

        return $this->json($response, [
            'run_id' => $runId,
            'status' => 'queued',
            'message' => 'Sync enqueued successfully.',
            'reused' => false,
        ], 202);
    }

    public function getRun(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? null;
        
        if ($id === 'last') {
            $statement = $this->connection->query('SELECT id FROM qs_sync_runs ORDER BY started_at DESC NULLS LAST LIMIT 1');
            $id = $statement->fetchColumn();
            if (!$id) {
                return $this->json($response, ['error' => 'No runs found'], 404);
            }
        } elseif (!$id) {
            return $this->json($response, ['error' => 'Missing run ID'], 400);
        }

        $statement = $this->connection->prepare(
            'SELECT * FROM qs_sync_runs WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $run = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$run) {
            return $this->json($response, ['error' => 'Run not found'], 404);
        }

        $sourcesStmt = $this->connection->prepare(
            'SELECT s.sheet_name, s.purpose, r.status, r.rows_seen, r.rows_imported, r.duration_ms, r.error_message
             FROM qs_sheet_import_runs r
             JOIN qs_sheet_sources s ON s.id = r.source_id
             WHERE r.sync_run_id = :id
             ORDER BY r.id ASC'
        );
        $sourcesStmt->execute(['id' => $id]);
        $run['sources'] = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->json($response, $run);
    }

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

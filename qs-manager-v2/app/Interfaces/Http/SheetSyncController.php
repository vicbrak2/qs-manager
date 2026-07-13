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

        $result = $this->importer->importAll();

        return $this->json($response, [
            'sync' => $result->toArray(),
        ]);
    }

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

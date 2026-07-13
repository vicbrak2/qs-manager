<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

final class HealthController
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function register(App $app): void
    {
        $app->get('/health', [$this, 'show']);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dbStatus = 'ok';

        try {
            $this->connection->query('select 1');
        } catch (\Throwable) {
            $dbStatus = 'error';
        }

        $payload = [
            'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
            'app' => getenv('APP_NAME') ?: 'QS Manager V2',
            'environment' => getenv('APP_ENV') ?: 'local',
            'database' => $dbStatus,
            'external_services' => [
                'enabled' => false,
                'llm' => false,
                'qdrant' => false,
                'whatsapp' => false,
                'sheets_read_sync' => getenv('SHEETS_READ_SYNC_ENABLED') === 'true',
                'sheets_write_sync' => false,
            ],
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

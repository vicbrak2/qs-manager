<?php

declare(strict_types=1);

namespace QSManager\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Http\AppFactory;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Base compartida para los tests de rutas HTTP (Fase 5 del plan de
 * migracion, extraida de HttpRoutesTest.php): conexion transaccional
 * (rollback automatico por test), app Slim real, y los dos helpers
 * `json()`/`payload()` que hacian requests falsas contra la app.
 *
 * KISS: es una clase base con 3 helpers, no un framework de tests --
 * cada *RoutesTest.php sigue escribiendo sus propios metodos test*() con
 * sus propios asserts, esto solo evita repetir el setUp/tearDown/json/payload
 * identico en los 5 archivos.
 */
abstract class HttpTestCase extends TestCase
{
    protected PDO $connection;
    protected App $app;

    public static function setUpBeforeClass(): void
    {
        if (!in_array('mock-gas', stream_get_wrappers(), true)) {
            stream_wrapper_register('mock-gas', MockGasStreamWrapper::class);
        }
    }

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::fromEnvironment();
        $this->connection->beginTransaction();
        $this->app = AppFactory::create($this->connection);
        MockGasStreamWrapper::reset();
    }

    protected function tearDown(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    protected function json(string $method, string $path, ?array $payload = null, ?App $app = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path)
            ->withHeader('Content-Type', 'application/json');

        if ($payload !== null) {
            $request = $request->withBody(
                (new StreamFactory())->createStream((string) json_encode($payload))
            );
        }

        $appToUse = $app ?? $this->app;
        return $appToUse->handle($request);
    }

    protected function payload(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

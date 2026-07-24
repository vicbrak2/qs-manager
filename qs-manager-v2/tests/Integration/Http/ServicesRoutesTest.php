<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Http;

use QSManager\Application\ServicesCatalog\CreateService;
use QSManager\Application\ServicesCatalog\ListServices;
use QSManager\Application\ServicesCatalog\ServiceCatalogGateway;
use QSManager\Infrastructure\Gas\HttpGasServiceCatalogGateway;
use QSManager\Infrastructure\Persistence\Postgres\PostgresServiceRepository;
use QSManager\Interfaces\Http\ServicesController;
use QSManager\Tests\Support\HttpTestCase;
use QSManager\Tests\Support\MockGasStreamWrapper;

/**
 * Extraido de HttpRoutesTest.php (Fase 5 del plan de migracion). Rutas
 * /api/v1/services y el gateway de publicacion hacia GAS.
 */
final class ServicesRoutesTest extends HttpTestCase
{
    public function testServicesRoutesValidateAndCreate(): void
    {
        $response = $this->json('GET', '/api/v1/services');
        self::assertSame(200, $response->getStatusCode());

        $invalid = $this->json('POST', '/api/v1/services', [
            'name' => 'ab',
            'category' => str_repeat('x', 81),
            'duration_minutes' => 0,
            'quantity' => 0,
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        $invalidPayload = $this->payload($invalid);
        self::assertArrayHasKey('name', $invalidPayload['errors']);
        self::assertArrayHasKey('category', $invalidPayload['errors']);
        self::assertArrayHasKey('duration_minutes', $invalidPayload['errors']);
        self::assertArrayHasKey('quantity', $invalidPayload['errors']);

        $created = $this->json('POST', '/api/v1/services', [
            'name' => 'Maquillaje social',
            'category' => 'maquillaje',
            'duration_minutes' => 90,
            'quantity' => 2,
        ]);
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('Maquillaje social', $this->payload($created)['service']['name']);
        self::assertSame(2, $this->payload($created)['service']['quantity']);
    }

    public function testPublishedServiceIsStoredAsMasterProjection(): void
    {
        $repository = new PostgresServiceRepository($this->connection);
        $gateway = new class implements ServiceCatalogGateway {
            public array $received = [];

            public function create(array $service, string $idempotencyKey): array
            {
                $this->received = $service;

                return [
                    'service_id' => 'SVC-9998',
                    'master_row' => 9998,
                    'accounting_row' => 9998,
                    'margin_status' => 'VERDE',
                ];
            }
        };
        $app = \Slim\Factory\AppFactory::create();
        $app->addBodyParsingMiddleware();
        (new ServicesController(
            new CreateService($repository),
            new ListServices($repository),
            $repository,
            catalogGateway: $gateway,
        ))->register($app);

        $response = $this->json('POST', '/api/v1/services', [
            'name' => 'Servicio publicado de prueba',
            'category' => 'Social',
            'duration_minutes' => 90,
            'quantity' => 1,
            'sale_price' => 100000,
            'total_cost' => 65000,
        ], $app);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->payload($response);
        self::assertTrue($payload['published_to_sheets']);
        self::assertSame('Servicios_Master', $payload['service']['source_sheet']);
        self::assertSame(9998, $payload['service']['source_row']);
        self::assertEquals(35000, $payload['service']['utility']);
        self::assertEquals(0.35, $payload['service']['margin_percent']);
        self::assertSame(100000, $gateway->received['sale_price']);
    }

    public function testCatalogGatewaySendsProtectedIdempotentPayload(): void
    {
        MockGasStreamWrapper::addResponse(json_encode([
            'ok' => true,
            'result' => ['service_id' => 'SVC-0100', 'master_row' => 100],
        ], JSON_THROW_ON_ERROR));
        $gateway = new HttpGasServiceCatalogGateway('mock-gas://catalog', 'secret-test');

        $result = $gateway->create([
            'name' => 'Servicio GAS',
            'sale_price' => 80000,
            'total_cost' => 50000,
        ], 'request-123');

        self::assertSame('SVC-0100', $result['service_id']);
        $request = MockGasStreamWrapper::getRequests()[0]['http'];
        $sent = json_decode($request['content'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('create_service', $sent['action']);
        self::assertSame('secret-test', $sent['api_key']);
        self::assertSame('request-123', $sent['idempotency_key']);
    }

    public function testServicesCanBeUpdatedAndDeleted(): void
    {
        $created = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Servicio editable',
            'category' => 'agenda',
            'duration_minutes' => 45,
        ]))['service'];

        $updated = $this->json('PUT', '/api/v1/services/' . $created['id'], [
            'name' => 'Servicio actualizado',
            'category' => 'novias',
            'duration_minutes' => 90,
            'quantity' => 3,
            'active' => true,
            'sale_price' => 120000,
            'total_cost' => 50000,
            'utility' => 70000,
            'margin_percent' => 0.58,
            'margin_status' => 'ok',
        ]);

        self::assertSame(200, $updated->getStatusCode());
        $payload = $this->payload($updated);
        self::assertSame('Servicio actualizado', $payload['service']['name']);
        self::assertEquals(120000, $payload['service']['sale_price']);
        self::assertSame(3, $payload['service']['quantity']);

        $deleted = $this->json('DELETE', '/api/v1/services/' . $created['id']);
        self::assertSame(200, $deleted->getStatusCode());
        self::assertTrue($this->payload($deleted)['deleted']);
    }

    public function testServicesStoreValidationExhaustive(): void
    {
        // 1. Missing name
        $res = $this->json('POST', '/api/v1/services', []);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('name', $this->payload($res)['errors']);

        // 2. Name too long (> 160 characters)
        $res = $this->json('POST', '/api/v1/services', [
            'name' => str_repeat('a', 161),
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('name', $this->payload($res)['errors']);

        // 3. Category too long (> 80 characters)
        $res = $this->json('POST', '/api/v1/services', [
            'name' => 'Valid Name',
            'category' => str_repeat('c', 81),
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('category', $this->payload($res)['errors']);

        // 4. Duration minutes negative
        $res = $this->json('POST', '/api/v1/services', [
            'name' => 'Valid Name',
            'duration_minutes' => -5,
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('duration_minutes', $this->payload($res)['errors']);
    }

    public function testServicesUpdateValidationExhaustive(): void
    {
        // 1. Service not found
        $res = $this->json('PUT', '/api/v1/services/99999', [
            'name' => 'Valid Name',
        ]);
        self::assertSame(404, $res->getStatusCode());

        // 2. Service ID not positive integer
        $res = $this->json('PUT', '/api/v1/services/abc', [
            'name' => 'Valid Name',
        ]);
        self::assertSame(422, $res->getStatusCode());

        // 3. Create a service to update
        $created = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Valid Name',
        ]))['service'];

        // 4. Update with validation errors (name too short, category too long, active not bool)
        $res = $this->json('PUT', '/api/v1/services/' . $created['id'], [
            'name' => 'ab',
            'category' => str_repeat('c', 81),
            'active' => 'not-a-bool',
        ]);
        self::assertSame(422, $res->getStatusCode());
        $errors = $this->payload($res)['errors'];
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('category', $errors);
        self::assertArrayHasKey('active', $errors);
    }
}

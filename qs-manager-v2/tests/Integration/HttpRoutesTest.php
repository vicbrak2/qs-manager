<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Http\AppFactory;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class HttpRoutesTest extends TestCase
{
    private PDO $connection;
    private App $app;

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

    public function testServicesRoutesValidateAndCreate(): void
    {
        $response = $this->json('GET', '/api/v1/services');
        self::assertSame(200, $response->getStatusCode());

        $invalid = $this->json('POST', '/api/v1/services', [
            'name' => 'ab',
            'category' => str_repeat('x', 81),
            'duration_minutes' => 0,
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        $invalidPayload = $this->payload($invalid);
        self::assertArrayHasKey('name', $invalidPayload['errors']);
        self::assertArrayHasKey('category', $invalidPayload['errors']);
        self::assertArrayHasKey('duration_minutes', $invalidPayload['errors']);

        $created = $this->json('POST', '/api/v1/services', [
            'name' => 'Maquillaje social',
            'category' => 'maquillaje',
            'duration_minutes' => 90,
        ]);
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('Maquillaje social', $this->payload($created)['service']['name']);
    }

    public function testTeamRoutesValidateAndCreate(): void
    {
        $response = $this->json('GET', '/api/v1/team');
        self::assertSame(200, $response->getStatusCode());

        $invalid = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Ca',
            'role' => 'Owner',
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        $invalidPayload = $this->payload($invalid);
        self::assertArrayHasKey('display_name', $invalidPayload['errors']);
        self::assertArrayHasKey('role', $invalidPayload['errors']);

        $created = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Camila Villalobos',
            'role' => 'coordinadora',
        ]);
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('coordinadora', $this->payload($created)['staff_member']['role']);
    }

    public function testBookingRoutesValidateReferencesAndCreate(): void
    {
        $response = $this->json('GET', '/api/v1/bookings');
        self::assertSame(200, $response->getStatusCode());

        $missingStatus = $this->json('POST', '/api/v1/bookings', []);
        self::assertSame(422, $missingStatus->getStatusCode());
        self::assertArrayHasKey('status', $this->payload($missingStatus)['errors']);

        $badPayload = $this->json('POST', '/api/v1/bookings', [
            'service_id' => 999,
            'staff_id' => 999,
            'customer_phone' => 'abc',
            'status' => 'draft',
        ]);
        self::assertSame(422, $badPayload->getStatusCode());
        $badPayloadBody = $this->payload($badPayload);
        self::assertArrayHasKey('service_id', $badPayloadBody['errors']);
        self::assertArrayHasKey('staff_id', $badPayloadBody['errors']);
        self::assertArrayHasKey('customer_phone', $badPayloadBody['errors']);

        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Peinado ondas',
            'category' => 'peinado',
            'duration_minutes' => 60,
        ]))['service']['id'];

        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Camila Villalobos',
            'role' => 'staff',
        ]))['staff_member']['id'];

        $created = $this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_name' => 'Juan Perez',
            'customer_phone' => '+56912345678',
            'scheduled_for' => '2026-07-20T14:30:00Z',
            'status' => 'confirmed',
            'address' => 'Av. Siempre Viva 123',
            'comuna' => 'Providencia',
            'service_value' => 85000,
            'transfer_value' => 12000,
            'deposit_amount' => 30000,
            'total_service' => 97000,
            'balance_due' => 67000,
            'payment_status' => 'abonado',
            'service_status' => 'agendado',
            'contract_id' => 'QS-2026-001',
            'milestone' => 'reserva',
            'cash_group' => 'servicios',
        ]);

        self::assertSame(201, $created->getStatusCode());
        $createdPayload = $this->payload($created);
        self::assertSame('confirmed', $createdPayload['booking']['status']);
        self::assertSame($serviceId, $createdPayload['booking']['service_id']);
        self::assertSame($staffId, $createdPayload['booking']['staff_id']);
        self::assertSame('Providencia', $createdPayload['booking']['comuna']);
        self::assertEquals(97000.0, $createdPayload['booking']['total_service']);

        $invalidMoney = $this->json('POST', '/api/v1/bookings', [
            'status' => 'draft',
            'total_service' => -1,
        ]);
        self::assertSame(422, $invalidMoney->getStatusCode());
        self::assertArrayHasKey('total_service', $this->payload($invalidMoney)['errors']);

        $sync = $this->json('POST', '/api/v1/bookings/' . $createdPayload['booking']['id'] . '/sync-gas');
        self::assertSame(202, $sync->getStatusCode());
        $syncPayload = $this->payload($sync);
        self::assertSame('skipped', $syncPayload['sync']['status']);
        self::assertFalse($syncPayload['sync']['configured']);
        self::assertSame('Providencia', $syncPayload['sync']['payload']['comuna']);
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

        $deleted = $this->json('DELETE', '/api/v1/services/' . $created['id']);
        self::assertSame(200, $deleted->getStatusCode());
        self::assertTrue($this->payload($deleted)['deleted']);
    }

    public function testBookingsCanBeUpdatedAndDeleted(): void
    {
        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Maquillaje editable',
            'category' => 'maquillaje',
            'duration_minutes' => 60,
        ]))['service']['id'];

        $booking = $this->payload($this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'customer_name' => 'Cliente inicial',
            'status' => 'draft',
        ]))['booking'];

        $updated = $this->json('PUT', '/api/v1/bookings/' . $booking['id'], [
            'service_id' => $serviceId,
            'staff_id' => null,
            'customer_name' => 'Cliente actualizado',
            'customer_phone' => '+56911111111',
            'scheduled_for' => '2026-08-01T10:00:00Z',
            'status' => 'confirmed',
            'address' => 'Direccion demo 123',
            'comuna' => 'Las Condes',
            'service_value' => 90000,
            'transfer_value' => 10000,
            'deposit_amount' => 30000,
            'total_service' => 100000,
            'balance_due' => 70000,
            'payment_status' => 'parcial',
            'service_status' => 'agendado',
            'contract_id' => 'QS-TEST',
            'milestone' => 'reserva',
            'cash_group' => 'agenda',
        ]);

        self::assertSame(200, $updated->getStatusCode());
        $payload = $this->payload($updated);
        self::assertSame('Cliente actualizado', $payload['booking']['customer_name']);
        self::assertSame('Las Condes', $payload['booking']['comuna']);

        $deleted = $this->json('DELETE', '/api/v1/bookings/' . $booking['id']);
        self::assertSame(200, $deleted->getStatusCode());
        self::assertTrue($this->payload($deleted)['deleted']);
    }

    public function testBookingCustomerPhoneExceedsMaxLengthValidation(): void
    {
        $response = $this->json('POST', '/api/v1/bookings', [
            'status' => 'draft',
            'customer_phone' => str_repeat('9', 41),
        ]);

        self::assertSame(422, $response->getStatusCode());
        $payload = $this->payload($response);
        self::assertArrayHasKey('errors', $payload);
        self::assertArrayHasKey('customer_phone', $payload['errors']);
        self::assertContains('Customer phone cannot exceed 40 characters.', $payload['errors']['customer_phone']);
    }

    public function testSheetsSyncStatusIsReadOnly(): void
    {
        $response = $this->json('GET', '/api/v1/sync/sheets/status');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->payload($response);
        self::assertSame('read_only', $payload['mode']);
        self::assertFalse($payload['writes_to_sheets']);
    }

    public function testGasSyncSuccess(): void
    {
        putenv('GAS_WEBAPP_URL=mock-gas://sync-endpoint');
        $app = AppFactory::create($this->connection);

        // 1. Create a service and team member
        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Peinado trenzas',
            'category' => 'peinado',
            'duration_minutes' => 60,
        ], $app))['service']['id'];

        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Maria Lopez',
            'role' => 'staff',
        ], $app))['staff_member']['id'];

        // 2. Create a booking (it should trigger sync internally)
        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse(json_encode(['ok' => true, 'status' => 'success']));

        $created = $this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_name' => 'Ana Maria',
            'customer_phone' => '+56987654321',
            'scheduled_for' => '2026-08-20T14:30:00Z',
            'status' => 'confirmed',
        ], $app);

        self::assertSame(201, $created->getStatusCode());
        $booking = $this->payload($created)['booking'];

        // 3. Verify POST was sent to GAS and verify the exact JSON payload layout
        $requests = MockGasStreamWrapper::getRequests();
        self::assertCount(1, $requests);
        $req = $requests[0];
        self::assertSame('POST', $req['http']['method']);
        $body = json_decode($req['http']['content'], true);
        
        self::assertSame('qs-manager-v2', $body['source']);
        self::assertSame($booking['id'], $body['id']);
        self::assertSame($booking['service_id'], $body['service_id']);
        self::assertSame('Peinado trenzas', $body['service_name']);
        self::assertSame($booking['staff_id'], $body['staff_id']);
        self::assertSame('Maria Lopez', $body['staff_name']);
        self::assertSame('Ana Maria', $body['customer_name']);
        self::assertSame('+56987654321', $body['customer_phone']);
        self::assertSame('2026-08-20', $body['fecha']);
        self::assertSame('14:30', $body['hora']);
        self::assertSame('confirmed', $body['status']);
        self::assertSame('Servicio', $body['tipo']);

        // 4. Verify DB was updated
        $stmt = $this->connection->prepare('SELECT gas_last_sync_status FROM qs_bookings WHERE id = ?');
        $stmt->execute([$booking['id']]);
        self::assertSame('synced', $stmt->fetchColumn());

        // 5. Test manual sync endpoint returns 200
        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse(json_encode(['ok' => true, 'status' => 'success']));

        $sync = $this->json('POST', '/api/v1/bookings/' . $booking['id'] . '/sync-gas', null, $app);
        self::assertSame(200, $sync->getStatusCode());
        $syncPayload = $this->payload($sync);
        self::assertSame('synced', $syncPayload['sync']['status']);
        self::assertTrue($syncPayload['sync']['success']);
        
        // Clean up environment
        putenv('GAS_WEBAPP_URL');
    }

    public function testGasSyncOnUpdateSuccess(): void
    {
        putenv('GAS_WEBAPP_URL=mock-gas://sync-endpoint');
        $app = AppFactory::create($this->connection);

        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Peinado trenzas 2',
            'category' => 'peinado',
            'duration_minutes' => 60,
        ], $app))['service']['id'];

        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Maria Lopez 2',
            'role' => 'staff',
        ], $app))['staff_member']['id'];

        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse(json_encode(['ok' => true, 'status' => 'success']));

        $created = $this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_name' => 'Ana Maria 2',
            'customer_phone' => '+56987654321',
            'scheduled_for' => '2026-08-20T14:30:00Z',
            'status' => 'confirmed',
        ], $app);

        self::assertSame(201, $created->getStatusCode());
        $bookingId = $this->payload($created)['booking']['id'];

        // Reset mock and record requests for update
        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse(json_encode(['ok' => true, 'status' => 'success']));

        $updated = $this->json('PUT', '/api/v1/bookings/' . $bookingId, [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_name' => 'Ana Maria Editada',
            'customer_phone' => '+56987654322',
            'scheduled_for' => '2026-08-20T15:30:00Z',
            'status' => 'confirmed',
        ], $app);

        self::assertSame(200, $updated->getStatusCode());
        
        $requests = MockGasStreamWrapper::getRequests();
        self::assertCount(1, $requests);
        $body = json_decode($requests[0]['http']['content'], true);
        
        // Assert exact JSON payload format on update
        self::assertSame('qs-manager-v2', $body['source']);
        self::assertSame($bookingId, $body['id']);
        self::assertSame($serviceId, $body['service_id']);
        self::assertSame('Peinado trenzas 2', $body['service_name']);
        self::assertSame($staffId, $body['staff_id']);
        self::assertSame('Maria Lopez 2', $body['staff_name']);
        self::assertSame('Ana Maria Editada', $body['customer_name']);
        self::assertSame('+56987654322', $body['customer_phone']);
        self::assertSame('2026-08-20', $body['fecha']);
        self::assertSame('15:30', $body['hora']);
        self::assertSame('confirmed', $body['status']);
        self::assertSame('Servicio', $body['tipo']);

        putenv('GAS_WEBAPP_URL');
    }

    public function testBookingCreationDoesNotCrashOnGasTimeout(): void
    {
        putenv('GAS_WEBAPP_URL=mock-gas://sync-endpoint');
        $app = AppFactory::create($this->connection);

        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Corte VarÃ³n',
            'duration_minutes' => 30,
        ], $app))['service']['id'];

        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse('TIMEOUT_ERROR');

        $response = $this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'customer_name' => 'Pedro Soto',
            'status' => 'confirmed',
        ], $app);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->payload($response);
        self::assertArrayHasKey('booking', $payload);
        self::assertArrayHasKey('warning', $payload);
        self::assertStringContainsString('GAS sync failed', $payload['warning']);

        // Verify DB contains status failed
        $stmt = $this->connection->prepare('SELECT gas_last_sync_status FROM qs_bookings WHERE id = ?');
        $stmt->execute([$payload['booking']['id']]);
        self::assertSame('failed', $stmt->fetchColumn());

        putenv('GAS_WEBAPP_URL');
    }

    public function testBookingUpdateDoesNotCrashOnGasTimeout(): void
    {
        putenv('GAS_WEBAPP_URL=mock-gas://sync-endpoint');
        $app = AppFactory::create($this->connection);

        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Corte VarÃ³n',
            'duration_minutes' => 30,
        ], $app))['service']['id'];

        $booking = $this->payload($this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'customer_name' => 'Pedro Soto',
            'status' => 'draft',
        ], $app))['booking'];

        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse('TIMEOUT_ERROR');

        $response = $this->json('PUT', '/api/v1/bookings/' . $booking['id'], [
            'service_id' => $serviceId,
            'customer_name' => 'Pedro Soto Actualizado',
            'status' => 'confirmed',
        ], $app);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->payload($response);
        self::assertArrayHasKey('booking', $payload);
        self::assertArrayHasKey('warning', $payload);
        self::assertStringContainsString('GAS sync failed', $payload['warning']);

        // Verify DB contains status failed
        $stmt = $this->connection->prepare('SELECT gas_last_sync_status FROM qs_bookings WHERE id = ?');
        $stmt->execute([$booking['id']]);
        self::assertSame('failed', $stmt->fetchColumn());

        putenv('GAS_WEBAPP_URL');
    }

    public function testGasSyncNetworkTimeout(): void
    {
        putenv('GAS_WEBAPP_URL=mock-gas://sync-endpoint');
        $app = AppFactory::create($this->connection);

        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Corte VarÃ³n',
            'duration_minutes' => 30,
        ], $app))['service']['id'];

        $booking = $this->payload($this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'customer_name' => 'Pedro Soto',
            'status' => 'confirmed',
        ], $app))['booking'];

        // Trigger manual sync with TIMEOUT_ERROR mock
        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse('TIMEOUT_ERROR');

        $sync = $this->json('POST', '/api/v1/bookings/' . $booking['id'] . '/sync-gas', null, $app);
        self::assertSame(202, $sync->getStatusCode());
        $syncPayload = $this->payload($sync);
        self::assertSame('failed', $syncPayload['sync']['status']);
        self::assertFalse($syncPayload['sync']['success']);

        // Verify DB updated to failed
        $stmt = $this->connection->prepare('SELECT gas_last_sync_status FROM qs_bookings WHERE id = ?');
        $stmt->execute([$booking['id']]);
        self::assertSame('failed', $stmt->fetchColumn());

        putenv('GAS_WEBAPP_URL');
    }

    public function testGasSyncErrorResponse(): void
    {
        putenv('GAS_WEBAPP_URL=mock-gas://sync-endpoint');
        $app = AppFactory::create($this->connection);

        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Manicure',
            'duration_minutes' => 45,
        ], $app))['service']['id'];

        $booking = $this->payload($this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'customer_name' => 'Lucia Fuentes',
            'status' => 'confirmed',
        ], $app))['booking'];

        // Trigger manual sync with API Error mock
        MockGasStreamWrapper::reset();
        MockGasStreamWrapper::addResponse(json_encode(['ok' => false, 'error' => 'GAS error message']));

        $sync = $this->json('POST', '/api/v1/bookings/' . $booking['id'] . '/sync-gas', null, $app);
        self::assertSame(202, $sync->getStatusCode());
        $syncPayload = $this->payload($sync);
        self::assertSame('failed', $syncPayload['sync']['status']);
        self::assertSame('GAS error message', $syncPayload['sync']['message']);

        // Verify DB updated to failed
        $stmt = $this->connection->prepare('SELECT gas_last_sync_status FROM qs_bookings WHERE id = ?');
        $stmt->execute([$booking['id']]);
        self::assertSame('failed', $stmt->fetchColumn());

        putenv('GAS_WEBAPP_URL');
    }

    public function testBookingValidationInvalidScheduledFor(): void
    {
        $response = $this->json('POST', '/api/v1/bookings', [
            'status' => 'draft',
            'scheduled_for' => 'not-a-date',
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('scheduled_for', $this->payload($response)['errors']);
    }

    public function testBookingValidationInvalidStatus(): void
    {
        $response = $this->json('POST', '/api/v1/bookings', [
            'status' => 'invalid_status',
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('status', $this->payload($response)['errors']);
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

    public function testTeamStoreValidationExhaustive(): void
    {
        // 1. Display name missing
        $res = $this->json('POST', '/api/v1/team', [
            'role' => 'admin',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('display_name', $this->payload($res)['errors']);

        // 2. Display name too short
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Ca',
            'role' => 'admin',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('display_name', $this->payload($res)['errors']);

        // 3. Display name too long
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => str_repeat('a', 161),
            'role' => 'admin',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('display_name', $this->payload($res)['errors']);

        // 4. Role missing
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Camila',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('role', $this->payload($res)['errors']);

        // 5. Role invalid
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Camila',
            'role' => 'owner',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('role', $this->payload($res)['errors']);
    }

    public function testBookingStoreValidationExhaustive(): void
    {
        // 1. Customer name too long
        $res = $this->json('POST', '/api/v1/bookings', [
            'status' => 'draft',
            'customer_name' => str_repeat('a', 161),
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('customer_name', $this->payload($res)['errors']);

        // 2. Total service not numeric
        $res = $this->json('POST', '/api/v1/bookings', [
            'status' => 'draft',
            'total_service' => 'not-numeric',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('total_service', $this->payload($res)['errors']);
    }

    public function testBookingUpdateValidationExhaustive(): void
    {
        // 1. Booking not found
        $res = $this->json('PUT', '/api/v1/bookings/99999', [
            'status' => 'draft',
        ]);
        self::assertSame(404, $res->getStatusCode());

        // 2. Booking ID not positive integer
        $res = $this->json('PUT', '/api/v1/bookings/abc', [
            'status' => 'draft',
        ]);
        self::assertSame(422, $res->getStatusCode());

        // 3. Create a booking to update
        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Temp Service',
        ]))['service']['id'];

        $booking = $this->payload($this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'status' => 'draft',
        ]))['booking'];

        // 4. Update with invalid references
        $res = $this->json('PUT', '/api/v1/bookings/' . $booking['id'], [
            'service_id' => 9999,
            'staff_id' => 9999,
            'status' => 'draft',
        ]);
        self::assertSame(422, $res->getStatusCode());
        $errors = $this->payload($res)['errors'];
        self::assertArrayHasKey('service_id', $errors);
        self::assertArrayHasKey('staff_id', $errors);
    }

    public function testStaticAssetsAreServedCorrectly(): void
    {
        // El servidor PHP interno (php -S) mediante router.php debe entregar estos archivos
        // bypassando Slim framework y definiendo el Content-Type adecuado.
        
        $cssHeaders = get_headers('http://localhost:8080/assets/css/tokens.css', true);
        self::assertStringContainsString('200 OK', $cssHeaders[0]);
        self::assertStringContainsString('text/css', $cssHeaders['Content-Type']);

        $jsHeaders = get_headers('http://localhost:8080/assets/js/app.js', true);
        self::assertStringContainsString('200 OK', $jsHeaders[0]);
        self::assertStringContainsString('application/javascript', $jsHeaders['Content-Type'] ?? $jsHeaders['content-type'] ?? '');
        
        $notFoundHeaders = get_headers('http://localhost:8080/assets/css/does-not-exist.css', true);
        self::assertStringContainsString('404 Not Found', $notFoundHeaders[0]);
    }

    public function testWebDashboardHtmlContent(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));

        $html = (string) $response->getBody();

        // 1. Google Font Outfit
        self::assertStringContainsString('fonts.googleapis.com', $html);
        self::assertStringContainsString('Outfit', $html);

        // 2. Pagination controls
        self::assertStringContainsString('id="booking-per-page"', $html);
        self::assertStringContainsString('id="booking-prev-page"', $html);
        self::assertStringContainsString('Anterior', $html);
        self::assertStringContainsString('id="booking-next-page"', $html);
        self::assertStringContainsString('Siguiente', $html);
        self::assertStringContainsString('id="booking-page-indicator"', $html);

        // 3. Dropdowns for Service, Staff, and Status
        self::assertStringContainsString('id="booking-filter-service"', $html);
        self::assertStringContainsString('id="booking-filter-staff"', $html);
        self::assertStringContainsString('id="booking-filter-status"', $html);

        // 4. GAS sync row triggers
                
        // 5. Verificamos que se carga el CSS externo
        self::assertStringContainsString('href="/assets/css/main.css?v=3"', $html);

        // 6. Vanilla JS estÃ¡ desacoplado en app.js
        self::assertStringContainsString('src="/assets/js/app.js"', $html);

        // Global read-only Sheets sync and unambiguous local refresh actions.
        self::assertStringContainsString('id="sync-all"', $html);
        self::assertStringContainsString('Sincronizar todo', $html);
        self::assertStringContainsString('id="refresh-services">Recargar', $html);
        self::assertStringContainsString('id="refresh-bookings">Recargar', $html);
    }

    private function json(string $method, string $path, ?array $payload = null, ?App $app = null): ResponseInterface
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

    private function payload(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

class MockGasStreamWrapper
{
    public $context;
    public static array $responses = [];
    public static array $requests = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$requests = [];
    }

    public static function addResponse(string $body): void
    {
        self::$responses[] = $body;
    }

    public static function getRequests(): array
    {
        return self::$requests;
    }

    private string $data = '';
    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        if ($this->context) {
            $opts = stream_context_get_options($this->context);
            self::$requests[] = $opts;
        }

        $response = array_shift(self::$responses) ?? json_encode(['ok' => true, 'status' => 'success']);
        if ($response === 'TIMEOUT_ERROR') {
            return false;
        }
        $this->data = $response;
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat(): array
    {
        return [];
    }
}


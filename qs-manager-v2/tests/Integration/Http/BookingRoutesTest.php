<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Http;

use QSManager\Infrastructure\Http\AppFactory;
use QSManager\Tests\Support\HttpTestCase;
use QSManager\Tests\Support\MockGasStreamWrapper;

/**
 * Extraido de HttpRoutesTest.php (Fase 5 del plan de migracion).
 * Rutas /api/v1/bookings, incluida la sincronizacion con GAS.
 */
final class BookingRoutesTest extends HttpTestCase
{
    public function testOverlappingBookingForSameStaffIsRejected(): void
    {
        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Maquillaje novia',
            'category' => 'maquillaje',
            'duration_minutes' => 90,
        ]))['service']['id'];

        $staffAId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Fernanda Rojas',
            'role' => 'staff',
        ]))['staff_member']['id'];

        $staffBId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Valentina Muñoz',
            'role' => 'staff',
        ]))['staff_member']['id'];

        $base = [
            'service_id' => $serviceId,
            'customer_name' => 'Clienta base',
            'status' => 'confirmed',
        ];

        $first = $this->json('POST', '/api/v1/bookings', $base + [
            'staff_id' => $staffAId,
            'scheduled_for' => '2026-08-10T10:00:00Z',
        ]);
        self::assertSame(201, $first->getStatusCode());

        // 11:15 cae dentro del bloque 10:00-11:30 (duracion 90 del servicio,
        // no el default de 60) -> conflicto reportado sobre scheduled_for.
        $conflict = $this->json('POST', '/api/v1/bookings', $base + [
            'staff_id' => $staffAId,
            'customer_name' => 'Clienta en conflicto',
            'scheduled_for' => '2026-08-10T11:15:00Z',
        ]);
        self::assertSame(422, $conflict->getStatusCode());
        $conflictErrors = $this->payload($conflict)['errors'];
        self::assertArrayHasKey('scheduled_for', $conflictErrors);
        self::assertStringContainsString('Conflicto de horario', $conflictErrors['scheduled_for'][0]);
        self::assertStringContainsString('Maquillaje novia', $conflictErrors['scheduled_for'][0]);

        // Justo al terminar el bloque anterior (11:30) no hay solapamiento.
        $adjacent = $this->json('POST', '/api/v1/bookings', $base + [
            'staff_id' => $staffAId,
            'scheduled_for' => '2026-08-10T11:30:00Z',
        ]);
        self::assertSame(201, $adjacent->getStatusCode());

        // Mismo horario con otra profesional es valido.
        $otherStaff = $this->json('POST', '/api/v1/bookings', $base + [
            'staff_id' => $staffBId,
            'scheduled_for' => '2026-08-10T11:15:00Z',
        ]);
        self::assertSame(201, $otherStaff->getStatusCode());

        // Sin staff asignado no hay chequeo posible.
        $noStaff = $this->json('POST', '/api/v1/bookings', $base + [
            'scheduled_for' => '2026-08-10T11:15:00Z',
        ]);
        self::assertSame(201, $noStaff->getStatusCode());
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

    public function testBookingServiceCanBeMarkedCompleted(): void
    {
        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Servicio para liberar',
            'category' => 'maquillaje',
            'duration_minutes' => 60,
        ]))['service']['id'];

        $created = $this->payload($this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'customer_name' => 'Clienta terminada',
            'scheduled_for' => '2026-07-27T12:00:00Z',
            'status' => 'confirmed',
            'deposit_amount' => 60000,
            'total_service' => 112530,
            'balance_due' => 52530,
            'payment_status' => 'abonado',
            'service_status' => 'agendado',
        ]))['booking'];

        $completed = $this->json('POST', '/api/v1/bookings/' . $created['id'] . '/complete-service');
        self::assertSame(200, $completed->getStatusCode());

        $payload = $this->payload($completed);
        self::assertSame('completed', $payload['booking']['status']);
        self::assertSame('Realizado', $payload['booking']['service_status']);
        self::assertSame('skipped', $payload['sync']['status']);
        self::assertStringContainsString('libera', $payload['message']);
    }

    public function testBookingCanStoreCompressedTransferReceiptMetadata(): void
    {
        $created = $this->json('POST', '/api/v1/bookings', [
            'customer_name' => 'Clienta con comprobante',
            'status' => 'confirmed',
            'transfer_receipt' => [
                'data_url' => 'data:image/webp;base64,' . base64_encode('small-compressed-image'),
                'filename' => 'abono.webp',
            ],
        ]);

        self::assertSame(201, $created->getStatusCode());
        $booking = $this->payload($created)['booking'];
        self::assertTrue($booking['has_transfer_receipt']);
        self::assertSame('image/webp', $booking['transfer_receipt_mime']);
        self::assertSame('abono.webp', $booking['transfer_receipt_filename']);
        self::assertSame(strlen('small-compressed-image'), $booking['transfer_receipt_size']);
        self::assertArrayNotHasKey('transfer_receipt_image', $booking);
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
}

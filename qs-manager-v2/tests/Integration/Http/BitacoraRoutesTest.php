<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Http;

use QSManager\Tests\Support\HttpTestCase;

/**
 * Rutas /api/v1/bitacoras -- modulo nativo de V2 que reemplaza al
 * BitacoraController de V1 (WordPress).
 */
final class BitacoraRoutesTest extends HttpTestCase
{
    public function testBitacoraCanBeLinkedToBooking(): void
    {
        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Novia',
            'duration_minutes' => 90,
        ]))['service']['id'];

        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Fernanda Rojas',
            'role' => 'staff',
        ]))['staff_member']['id'];

        $booking = $this->payload($this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_name' => 'Camila Soto',
            'scheduled_for' => '2026-09-12T12:30:00Z',
            'status' => 'confirmed',
            'address' => 'Av. Siempre Viva 123',
            'comuna' => 'Buin',
            'total_service' => 100000,
        ]))['booking'];

        $created = $this->json('POST', '/api/v1/bitacoras', [
            'booking_id' => $booking['id'],
            'fecha_servicio' => '2026-09-12',
            'tipo_servicio' => 'Novia',
            'clienta_nombre' => 'Camila Soto',
            'direccion_servicio' => 'Av. Siempre Viva 123, Buin',
            'punto_salida' => 'Estudio Qamiluna',
            'mua_id' => $staffId,
            'precio_cliente_clp' => 100000,
        ]);

        self::assertSame(201, $created->getStatusCode());
        $bitacora = $this->payload($created)['bitacora'];
        self::assertSame($booking['id'], $bitacora['booking_id']);

        $shown = $this->payload($this->json('GET', '/api/v1/bitacoras/' . $bitacora['id']))['bitacora'];
        self::assertSame($booking['id'], $shown['booking_id']);

        $bookings = $this->payload($this->json('GET', '/api/v1/bookings'))['bookings'];
        $linked = array_values(array_filter($bookings, static fn (array $item): bool => $item['id'] === $booking['id']))[0];
        self::assertSame($bitacora['id'], $linked['bitacora_id']);

        $duplicate = $this->json('POST', '/api/v1/bitacoras', [
            'booking_id' => $booking['id'],
            'fecha_servicio' => '2026-09-12',
            'tipo_servicio' => 'Novia',
            'clienta_nombre' => 'Camila Soto',
            'direccion_servicio' => 'Av. Siempre Viva 123, Buin',
            'punto_salida' => 'Estudio Qamiluna',
            'mua_id' => $staffId,
        ]);
        self::assertSame(422, $duplicate->getStatusCode());
        self::assertStringContainsString('ya tiene bitácora', $this->payload($duplicate)['error']);
    }

    public function testBitacoraRejectsUnknownBookingId(): void
    {
        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Fernanda Rojas',
            'role' => 'staff',
        ]))['staff_member']['id'];

        $response = $this->json('POST', '/api/v1/bitacoras', [
            'booking_id' => 999999,
            'fecha_servicio' => '2026-09-12',
            'tipo_servicio' => 'Novia',
            'clienta_nombre' => 'Camila Soto',
            'direccion_servicio' => 'Av. Siempre Viva 123, Buin',
            'punto_salida' => 'Estudio Qamiluna',
            'mua_id' => $staffId,
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('booking_id', $this->payload($response)['errors']);
    }

    public function testBitacoraCrudNotesAndSummary(): void
    {
        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Daniela Fuentes',
            'role' => 'staff',
        ]))['staff_member']['id'];

        $base = [
            'fecha_servicio' => '2026-09-12',
            'tipo_servicio' => 'Novia',
            'clienta_nombre' => 'Camila Soto',
            'direccion_servicio' => 'Av. Siempre Viva 123, Buin',
            'punto_salida' => 'Estudio Qamiluna',
            'tiempo_traslado_min' => 45,
            'hora_llegada' => '07:30',
            'costo_staff_clp' => 60000,
            'precio_cliente_clp' => 100000,
        ];

        // Campos requeridos ausentes -> 422 con errores por campo.
        $missing = $this->json('POST', '/api/v1/bitacoras', []);
        self::assertSame(422, $missing->getStatusCode());
        $missingErrors = $this->payload($missing)['errors'];
        foreach (['fecha_servicio', 'tipo_servicio', 'clienta_nombre', 'direccion_servicio', 'punto_salida'] as $field) {
            self::assertArrayHasKey($field, $missingErrors);
        }

        // Politica de dominio: sin equipo asignado -> 422 con el mensaje de la policy.
        $noTeam = $this->json('POST', '/api/v1/bitacoras', $base);
        self::assertSame(422, $noTeam->getStatusCode());
        self::assertStringContainsString('equipo asignado', $this->payload($noTeam)['error']);

        // Staff inexistente -> 422 sobre mua_id.
        $badStaff = $this->json('POST', '/api/v1/bitacoras', $base + ['mua_id' => 99999]);
        self::assertSame(422, $badStaff->getStatusCode());
        self::assertArrayHasKey('mua_id', $this->payload($badStaff)['errors']);

        // Alta valida.
        $created = $this->json('POST', '/api/v1/bitacoras', $base + ['mua_id' => $staffId]);
        self::assertSame(201, $created->getStatusCode());
        $bitacora = $this->payload($created)['bitacora'];
        self::assertSame('Camila Soto', $bitacora['clienta_nombre']);
        self::assertSame(40000, $bitacora['projected_margin_clp']);
        self::assertSame(45, $bitacora['route_plan']['travel_duration_min']);
        self::assertTrue($bitacora['route_plan']['recommended_minimum_met']);
        $id = $bitacora['id'];

        // Listado y detalle.
        $list = $this->payload($this->json('GET', '/api/v1/bitacoras'))['bitacoras'];
        self::assertSame($id, $list[0]['id']);

        $shown = $this->json('GET', '/api/v1/bitacoras/' . $id);
        self::assertSame(200, $shown->getStatusCode());
        self::assertSame('Novia', $this->payload($shown)['bitacora']['tipo_servicio']);

        // Update: cambia pricing y recalcula margen.
        $updated = $this->json('PUT', '/api/v1/bitacoras/' . $id, [
            'mua_id' => $staffId,
            'precio_cliente_clp' => 120000,
        ] + $base);
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame(60000, $this->payload($updated)['bitacora']['projected_margin_clp']);

        // Notas de traslado.
        $emptyNote = $this->json('POST', '/api/v1/bitacoras/' . $id . '/notes', []);
        self::assertSame(422, $emptyNote->getStatusCode());

        $note = $this->json('POST', '/api/v1/bitacoras/' . $id . '/notes', [
            'message' => 'Esperar en la reja principal',
        ]);
        self::assertSame(201, $note->getStatusCode());
        $withNote = $this->payload($note)['bitacora'];
        self::assertCount(1, $withNote['notes']);
        self::assertSame('Esperar en la reja principal', $withNote['notes'][0]['message']);

        // Resumen logistico.
        $summary = $this->payload($this->json('GET', '/api/v1/bitacoras/' . $id . '/summary'))['summary'];
        self::assertSame(60000, $summary['pricing']['projected_margin_clp']);
        self::assertSame(1, $summary['notes_count']);
        self::assertSame($staffId, $summary['team']['mua_id']);
        self::assertSame('Estudio Qamiluna', $summary['route_plan']['pickup_point']);

        // Inexistente -> 404.
        self::assertSame(404, $this->json('GET', '/api/v1/bitacoras/999999')->getStatusCode());
        self::assertSame(404, $this->json('PUT', '/api/v1/bitacoras/999999', $base)->getStatusCode());
        self::assertSame(404, $this->json('POST', '/api/v1/bitacoras/999999/notes', ['message' => 'x'])->getStatusCode());
    }
}

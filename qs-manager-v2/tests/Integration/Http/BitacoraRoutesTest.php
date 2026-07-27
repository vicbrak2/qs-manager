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
        foreach (['fecha_servicio', 'tipo_servicio', 'clienta_nombre', 'direccion_servicio'] as $field) {
            self::assertArrayHasKey($field, $missingErrors);
        }
        // punto_salida ya NO es obligatorio: cae al punto de salida habitual.
        self::assertArrayNotHasKey('punto_salida', $missingErrors);

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

    public function testTravelPlanDerivesDepartureFromLegsAndServiceStart(): void
    {
        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Paz Contreras',
            'role' => 'staff',
        ]))['staff_member']['id'];

        // Caso real de la operacion: servicio 16:00 con un tramo de 10 min
        // -> llegada 15:45 (15 antes) y salida 15:20 (15 + 10 + 15 holgura).
        $created = $this->json('POST', '/api/v1/bitacoras', [
            'fecha_servicio' => '2026-05-22',
            'tipo_servicio' => 'Prueba Novia (Maquillaje + Peinado)',
            'clienta_nombre' => 'Sara Martinez',
            'direccion_servicio' => 'Padre Fernando Cifuentes Grez 4861, Macul',
            'punto_salida' => 'Metro Macul',
            'mua_id' => $staffId,
            'hora_inicio_servicio' => '16:00',
            'hora_fin_servicio' => '18:00',
            'tramos' => [['destino' => 'Macul', 'minutos' => 10]],
            'objetivo' => 'Llegar con anticipacion para preparar materiales',
            'consideraciones' => 'Confirmar acceso al domicilio',
        ]);

        self::assertSame(201, $created->getStatusCode());
        $bitacora = $this->payload($created)['bitacora'];
        self::assertSame('15:45', $bitacora['hora_llegada_objetivo']);
        self::assertSame('15:20', $bitacora['hora_salida_sugerida']);
        self::assertSame([['destino' => 'Macul', 'minutos' => 10]], $bitacora['tramos']);
        // El total de traslado se deriva de los tramos, no del campo legacy.
        self::assertSame(10, $bitacora['route_plan']['travel_duration_min']);

        // Varios tramos: 16:00 - 15 - (20+25) - 15 = 14:45.
        $updated = $this->json('PUT', '/api/v1/bitacoras/' . $bitacora['id'], [
            'fecha_servicio' => '2026-05-22',
            'tipo_servicio' => 'Prueba Novia (Maquillaje + Peinado)',
            'clienta_nombre' => 'Sara Martinez',
            'direccion_servicio' => 'Padre Fernando Cifuentes Grez 4861, Macul',
            'punto_salida' => 'Estudio Qamiluna',
            'mua_id' => $staffId,
            'hora_inicio_servicio' => '16:00',
            'tramos' => [
                ['destino' => 'Metro Macul', 'minutos' => 20],
                ['destino' => 'La Florida', 'minutos' => 25],
            ],
        ]);
        self::assertSame(200, $updated->getStatusCode());
        $replanned = $this->payload($updated)['bitacora'];
        self::assertSame('14:45', $replanned['hora_salida_sugerida']);
        self::assertSame(45, $replanned['route_plan']['travel_duration_min']);

        // Sin hora de inicio no hay plan calculable, pero la bitacora vive.
        $sinHorario = $this->json('POST', '/api/v1/bitacoras', [
            'fecha_servicio' => '2026-05-23',
            'tipo_servicio' => 'Social',
            'clienta_nombre' => 'Otra clienta',
            'direccion_servicio' => 'Direccion 1',
            'punto_salida' => 'Estudio',
            'mua_id' => $staffId,
        ]);
        self::assertSame(201, $sinHorario->getStatusCode());
        $sinPlan = $this->payload($sinHorario)['bitacora'];
        self::assertNull($sinPlan['hora_llegada_objetivo']);
        self::assertNull($sinPlan['hora_salida_sugerida']);
        self::assertSame([], $sinPlan['tramos']);
    }

    public function testMinimumFieldsAreEnoughAndTheRestIsGenerated(): void
    {
        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Cami Verdejo',
            'role' => 'staff',
        ]))['staff_member']['id'];

        // Sin punto de salida, sin objetivo y sin consideraciones: el usuario
        // solo aporta el servicio y los tramos con sus tiempos.
        $created = $this->json('POST', '/api/v1/bitacoras', [
            'fecha_servicio' => '2026-07-27',
            'tipo_servicio' => 'Novia Civil Maquillaje Peinado',
            'clienta_nombre' => 'Nadia Palomino',
            'direccion_servicio' => 'Gerónimo de Alderete 208, depto 2004, La Florida',
            'mua_id' => $staffId,
            'hora_inicio_servicio' => '08:00',
            'tramos' => [
                ['destino' => 'Providencia', 'minutos' => 15, 'recoge' => 'Paz'],
                ['destino' => 'La Florida', 'minutos' => 25],
            ],
        ]);

        self::assertSame(201, $created->getStatusCode());
        $bitacora = $this->payload($created)['bitacora'];

        self::assertSame('Metro Macul', $bitacora['route_plan']['pickup_point']);
        self::assertStringContainsString('anticipacion', $bitacora['objetivo']);
        // Sale 06:50 (temprano) y la direccion es un depto: ambas notas aplican.
        self::assertStringContainsString('Salida temprana', $bitacora['consideraciones']);
        self::assertStringContainsString('acceso al edificio', $bitacora['consideraciones']);
        self::assertStringContainsString('lista en su punto', $bitacora['consideraciones']);

        self::assertSame('06:50', $bitacora['hora_salida_sugerida']);
        self::assertSame('07:45', $bitacora['hora_llegada_objetivo']);
        self::assertSame(
            [['recoge' => 'Paz', 'comuna' => 'Providencia', 'hora' => '07:11']],
            $bitacora['recogidas']
        );
    }

    public function testTravelPlanRejectsMalformedTimesAndLegs(): void
    {
        $base = [
            'fecha_servicio' => '2026-05-22',
            'tipo_servicio' => 'Novia',
            'clienta_nombre' => 'Sara Martinez',
            'direccion_servicio' => 'Direccion 1',
            'punto_salida' => 'Estudio',
        ];

        $badTime = $this->json('POST', '/api/v1/bitacoras', $base + ['hora_inicio_servicio' => '16 hrs']);
        self::assertSame(422, $badTime->getStatusCode());
        self::assertArrayHasKey('hora_inicio_servicio', $this->payload($badTime)['errors']);

        $badLegs = $this->json('POST', '/api/v1/bitacoras', $base + [
            'tramos' => [
                ['destino' => '', 'minutos' => 10],
                ['destino' => 'Tramo sin minutos', 'minutos' => -5],
            ],
        ]);
        self::assertSame(422, $badLegs->getStatusCode());
        self::assertCount(2, $this->payload($badLegs)['errors']['tramos']);
    }
}

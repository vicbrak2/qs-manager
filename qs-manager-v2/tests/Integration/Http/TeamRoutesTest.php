<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Http;

use QSManager\Tests\Support\HttpTestCase;

/**
 * Extraido de HttpRoutesTest.php (Fase 5 del plan de migracion).
 * Rutas /api/v1/team.
 */
final class TeamRoutesTest extends HttpTestCase
{
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

    public function testDeleteIsBlockedOnlyByPendingServices(): void
    {
        $crear = fn (string $nombre): int => $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => $nombre,
            'role' => 'staff',
        ]))['staff_member']['id'];

        $reservar = function (int $staffId, string $cuando, string $estado = 'confirmed'): void {
            $response = $this->json('POST', '/api/v1/bookings', [
                'staff_id' => $staffId,
                'customer_name' => 'Clienta',
                'scheduled_for' => $cuando,
                'status' => $estado,
            ]);
            self::assertSame(201, $response->getStatusCode());
        };

        $futuro = (new \DateTimeImmutable('+20 days'))->format(\DateTimeImmutable::ATOM);
        $pasado = (new \DateTimeImmutable('-60 days'))->format(\DateTimeImmutable::ATOM);

        // 1. Servicio futuro sin cerrar -> no se puede borrar, con el detalle.
        $conPendiente = $crear('Con Pendiente');
        $reservar($conPendiente, $futuro);
        $bloqueada = $this->json('DELETE', '/api/v1/team/' . $conPendiente);
        self::assertSame(409, $bloqueada->getStatusCode());
        $cuerpo = $this->payload($bloqueada);
        self::assertStringContainsString('servicio pendiente', $cuerpo['error']);
        self::assertCount(1, $cuerpo['pending_services']);

        // 2. Solo servicios pasados -> se borra, y el historial queda sin
        //    profesional en vez de impedir la baja.
        $soloPasado = $crear('Solo Pasado');
        $reservar($soloPasado, $pasado);
        self::assertSame(200, $this->json('DELETE', '/api/v1/team/' . $soloPasado)->getStatusCode());

        $historicas = array_filter(
            $this->payload($this->json('GET', '/api/v1/bookings'))['bookings'],
            static fn (array $b): bool => $b['scheduled_for'] !== null && $b['scheduled_for'] < date('c')
        );
        self::assertNotEmpty($historicas);
        foreach ($historicas as $booking) {
            self::assertNotSame($soloPasado, $booking['staff_id']);
        }

        // 3. Un servicio futuro cancelado no retiene a nadie.
        $conCancelado = $crear('Con Cancelado');
        $reservar($conCancelado, $futuro, 'cancelled');
        self::assertSame(200, $this->json('DELETE', '/api/v1/team/' . $conCancelado)->getStatusCode());

        // 4. Sin servicios -> se borra; borrarla de nuevo da 404.
        $sinServicios = $crear('Sin Servicios');
        self::assertSame(200, $this->json('DELETE', '/api/v1/team/' . $sinServicios)->getStatusCode());
        self::assertSame(404, $this->json('DELETE', '/api/v1/team/' . $sinServicios)->getStatusCode());
    }

    public function testStaffAvailabilityReflectsActiveBookings(): void
    {
        $staffId = $this->payload($this->json('POST', '/api/v1/team', [
            'display_name' => 'Antonia Reyes',
            'role' => 'staff',
        ]))['staff_member']['id'];

        $serviceId = $this->payload($this->json('POST', '/api/v1/services', [
            'name' => 'Peinado gala',
            'category' => 'peinado',
            'duration_minutes' => 90,
        ]))['service']['id'];

        $booking = $this->json('POST', '/api/v1/bookings', [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_name' => 'Clienta agenda',
            'scheduled_for' => '2026-08-15T10:00:00Z',
            'status' => 'confirmed',
        ]);
        self::assertSame(201, $booking->getStatusCode());

        // Sin hora pedida: available = "el dia esta libre" -> false con reserva.
        $day = $this->json('GET', '/api/v1/team/' . $staffId . '/availability?date=2026-08-15');
        self::assertSame(200, $day->getStatusCode());
        $dayPayload = $this->payload($day);
        self::assertFalse($dayPayload['available']);
        self::assertCount(1, $dayPayload['busy']);
        self::assertSame('2026-08-15T10:00:00+00:00', $dayPayload['busy'][0]['start_at']);
        self::assertSame('2026-08-15T11:30:00+00:00', $dayPayload['busy'][0]['end_at']);
        self::assertStringContainsString('Peinado gala', $dayPayload['busy'][0]['label']);

        // Hora dentro del bloque ocupado (10:00-11:30) -> no disponible.
        $within = $this->payload($this->json(
            'GET',
            '/api/v1/team/' . $staffId . '/availability?date=2026-08-15&time=11:00'
        ));
        self::assertFalse($within['available']);

        // Hora libre el mismo dia -> disponible.
        $free = $this->payload($this->json(
            'GET',
            '/api/v1/team/' . $staffId . '/availability?date=2026-08-15&time=12:00'
        ));
        self::assertTrue($free['available']);

        // Otro dia sin reservas -> disponible y sin bloques.
        $empty = $this->payload($this->json('GET', '/api/v1/team/' . $staffId . '/availability?date=2026-08-16'));
        self::assertTrue($empty['available']);
        self::assertSame([], $empty['busy']);

        // Fecha invalida -> 422; staff inexistente -> 404.
        self::assertSame(422, $this->json('GET', '/api/v1/team/' . $staffId . '/availability')->getStatusCode());
        self::assertSame(404, $this->json('GET', '/api/v1/team/999999/availability?date=2026-08-15')->getStatusCode());
    }
}

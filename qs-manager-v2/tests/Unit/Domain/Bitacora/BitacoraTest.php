<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\Bitacora;
use QSManager\Domain\Bitacora\PickupPoint;
use QSManager\Domain\Bitacora\RoutePlan;
use QSManager\Domain\Bitacora\ServiceAddress;
use QSManager\Domain\Bitacora\TravelDuration;
use QSManager\Domain\Bitacora\TravelNote;

final class BitacoraTest extends TestCase
{
    public function testHasAssignedTeamRequiresAtLeastOneRole(): void
    {
        $bitacora = $this->makeBitacora(muaId: null, estilistaId: null);
        $this->assertFalse($bitacora->hasAssignedTeam());

        $bitacora = $this->makeBitacora(muaId: 1, estilistaId: null);
        $this->assertTrue($bitacora->hasAssignedTeam());
    }

    public function testProjectedMarginIsPriceMinusStaffCost(): void
    {
        $bitacora = $this->makeBitacora(precioClienteClp: 100_000, costoStaffClp: 60_000);

        $this->assertSame(40_000, $bitacora->projectedMarginClp());
    }

    public function testCarriesTravelNotes(): void
    {
        $note = new TravelNote('Esperar en la reja principal', 5, new DateTimeImmutable('2026-07-01'));
        $bitacora = $this->makeBitacora(notes: [$note]);

        $this->assertCount(1, $bitacora->notes());
        $this->assertSame('Esperar en la reja principal', $bitacora->notes()[0]->message());
    }

    /**
     * @param list<TravelNote> $notes
     */
    private function makeBitacora(
        ?int $muaId = 1,
        ?int $estilistaId = null,
        int $precioClienteClp = 80_000,
        int $costoStaffClp = 50_000,
        array $notes = [],
    ): Bitacora {
        return new Bitacora(
            id: null,
            bookingId: null,
            bookingExternalId: null,
            fechaServicio: '2026-08-01',
            tipoServicio: 'Maquillaje novia',
            muaId: $muaId,
            estilistaId: $estilistaId,
            clientaNombre: 'Clienta de prueba',
            serviceAddress: new ServiceAddress('Av. Providencia 1234'),
            routePlan: new RoutePlan(new PickupPoint('Metro Manquehue'), null, new TravelDuration(20), null),
            notasLogisticas: null,
            costoStaffClp: $costoStaffClp,
            precioClienteClp: $precioClienteClp,
            notes: $notes,
            createdAt: new DateTimeImmutable('2026-07-01'),
            updatedAt: new DateTimeImmutable('2026-07-01'),
        );
    }
}

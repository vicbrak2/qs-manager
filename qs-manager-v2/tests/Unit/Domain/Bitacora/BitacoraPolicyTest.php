<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\Bitacora;
use QSManager\Domain\Bitacora\BitacoraPolicy;
use QSManager\Domain\Bitacora\PickupPoint;
use QSManager\Domain\Bitacora\RoutePlan;
use QSManager\Domain\Bitacora\ServiceAddress;
use QSManager\Domain\Bitacora\TravelDuration;

final class BitacoraPolicyTest extends TestCase
{
    private BitacoraPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new BitacoraPolicy();
    }

    public function testValidBitacoraHasNoErrors(): void
    {
        $bitacora = $this->makeBitacora();

        $this->assertTrue($this->policy->isSatisfiedBy($bitacora));
        $this->assertSame([], $this->policy->validate($bitacora));
    }

    public function testRequiresFechaServicio(): void
    {
        $bitacora = $this->makeBitacora(fechaServicio: '   ');

        $errors = $this->policy->validate($bitacora);

        $this->assertContains('La fecha de servicio es obligatoria.', $errors);
        $this->assertFalse($this->policy->isSatisfiedBy($bitacora));
    }

    public function testRequiresTipoServicio(): void
    {
        $bitacora = $this->makeBitacora(tipoServicio: '');

        $this->assertContains('El tipo de servicio es obligatorio.', $this->policy->validate($bitacora));
    }

    public function testRequiresAssignedTeam(): void
    {
        $bitacora = $this->makeBitacora(muaId: null, estilistaId: null);

        $this->assertContains('La bitacora requiere equipo asignado.', $this->policy->validate($bitacora));
    }

    public function testSatisfiedWithOnlyMuaAssigned(): void
    {
        $bitacora = $this->makeBitacora(muaId: 7, estilistaId: null);

        $this->assertTrue($this->policy->isSatisfiedBy($bitacora));
    }

    public function testSatisfiedWithOnlyEstilistaAssigned(): void
    {
        $bitacora = $this->makeBitacora(muaId: null, estilistaId: 9);

        $this->assertTrue($this->policy->isSatisfiedBy($bitacora));
    }

    public function testAccumulatesMultipleErrors(): void
    {
        $bitacora = $this->makeBitacora(fechaServicio: '', tipoServicio: '', muaId: null, estilistaId: null);

        $errors = $this->policy->validate($bitacora);

        $this->assertCount(3, $errors);
    }

    /**
     * Nota heredada de V1: PickupPoint::value() nunca puede ser un string
     * vacio (el constructor de PickupPoint ya lo rechaza), asi que la
     * regla "punto de salida obligatorio para domicilio" del policy nunca
     * se dispara en la practica -- es un chequeo redundante que ya existia
     * en V1 y se porto tal cual, sin cambiar el comportamiento.
     */
    public function testPickupPointRuleIsUnreachableByConstruction(): void
    {
        $bitacora = $this->makeBitacora();

        $this->assertNotContains(
            'El punto de salida es obligatorio para servicios a domicilio.',
            $this->policy->validate($bitacora)
        );
    }

    private function makeBitacora(
        string $fechaServicio = '2026-08-01',
        string $tipoServicio = 'Maquillaje novia',
        ?int $muaId = 1,
        ?int $estilistaId = 2,
    ): Bitacora {
        return new Bitacora(
            id: null,
            fechaServicio: $fechaServicio,
            tipoServicio: $tipoServicio,
            muaId: $muaId,
            estilistaId: $estilistaId,
            clientaNombre: 'Clienta de prueba',
            serviceAddress: new ServiceAddress('Av. Providencia 1234'),
            routePlan: new RoutePlan(
                new PickupPoint('Metro Manquehue'),
                null,
                new TravelDuration(20),
                null
            ),
            notasLogisticas: null,
            costoStaffClp: 50_000,
            precioClienteClp: 80_000,
            notes: [],
            createdAt: new DateTimeImmutable('2026-07-01'),
            updatedAt: new DateTimeImmutable('2026-07-01'),
        );
    }
}

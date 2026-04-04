<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Bitacora;

use DateTimeImmutable;
use QS\Modules\Bitacora\Domain\Entity\Bitacora;
use QS\Modules\Bitacora\Domain\Entity\RoutePlan;
use QS\Modules\Bitacora\Domain\Policy\BitacoraPolicy;
use QS\Modules\Bitacora\Domain\ValueObject\PickupPoint;
use QS\Modules\Bitacora\Domain\ValueObject\ServiceAddress;
use QS\Modules\Bitacora\Domain\ValueObject\TravelDuration;
use QS\Shared\Testing\TestCase;

final class BitacoraPolicyTest extends TestCase
{
    public function testRejectsBitacoraWithoutAssignedTeam(): void
    {
        $policy = new BitacoraPolicy();
        $bitacora = new Bitacora(
            null,
            '2026-04-04',
            'Maquillaje Social',
            null,
            null,
            'Ana Perez',
            new ServiceAddress('Providencia 123, Santiago'),
            new RoutePlan(new PickupPoint('Studio'), null, new TravelDuration(20), '09:00'),
            null,
            40000,
            70000,
            [],
            new DateTimeImmutable('2026-04-04 08:00:00'),
            new DateTimeImmutable('2026-04-04 08:00:00')
        );

        self::assertSame(
            ['La bitacora requiere equipo asignado.'],
            $policy->validate($bitacora)
        );
    }

    public function testAcceptsValidBitacora(): void
    {
        $policy = new BitacoraPolicy();
        $bitacora = new Bitacora(
            null,
            '2026-04-04',
            'Combo Social M+P',
            3,
            9,
            'Carla Soto',
            new ServiceAddress('Las Condes 456, Santiago'),
            new RoutePlan(new PickupPoint('Studio'), 'Primero Carla', new TravelDuration(30), '10:30'),
            'Considerar trafico',
            60000,
            90000,
            [],
            new DateTimeImmutable('2026-04-04 08:00:00'),
            new DateTimeImmutable('2026-04-04 08:00:00')
        );

        self::assertTrue($policy->isSatisfiedBy($bitacora));
    }
}

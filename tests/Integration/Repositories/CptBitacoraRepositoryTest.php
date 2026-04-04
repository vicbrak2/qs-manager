<?php

declare(strict_types=1);

namespace QS\Tests\Integration\Repositories;

use DateTimeImmutable;
use QS\Modules\Bitacora\Domain\Entity\Bitacora;
use QS\Modules\Bitacora\Domain\Entity\RoutePlan;
use QS\Modules\Bitacora\Domain\ValueObject\PickupPoint;
use QS\Modules\Bitacora\Domain\ValueObject\ServiceAddress;
use QS\Modules\Bitacora\Domain\ValueObject\TravelDuration;
use QS\Modules\Bitacora\Infrastructure\Persistence\CptBitacoraRepository;
use QS\Modules\Bitacora\Infrastructure\Persistence\MetaFieldMapper;
use QS\Shared\Testing\WpTestCase;

final class CptBitacoraRepositoryTest extends WpTestCase
{
    public function testSaveReturnsPersistedBitacora(): void
    {
        $this->requireWordPressRuntime();

        $repository = new CptBitacoraRepository(new MetaFieldMapper());
        $bitacora = new Bitacora(
            null,
            '2026-04-04',
            'Maquillaje Social',
            5,
            null,
            'Ana Perez',
            new ServiceAddress('Providencia 123, Santiago'),
            new RoutePlan(new PickupPoint('Studio'), null, new TravelDuration(20), '09:00'),
            'Prueba',
            40000,
            70000,
            [],
            new DateTimeImmutable('2026-04-04 08:00:00'),
            new DateTimeImmutable('2026-04-04 08:00:00')
        );
        $saved = $repository->save($bitacora);

        self::assertNotNull($saved->id());
        self::assertSame('Maquillaje Social', $saved->tipoServicio());
    }
}

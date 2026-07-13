<?php

declare(strict_types=1);

namespace QSManager\Tests\Domain\ServicesCatalog;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\ServicesCatalog\Service;
use QSManager\Domain\ServicesCatalog\ServiceDuration;
use QSManager\Domain\ServicesCatalog\ServiceName;

final class ServiceTest extends TestCase
{
    public function testCreatesValidService(): void
    {
        $service = Service::create('Maquillaje social', 'maquillaje', 90);

        self::assertSame('Maquillaje social', $service->name()->value());
        self::assertSame('maquillaje', $service->category());
        self::assertSame(90, $service->duration()?->minutes());
        self::assertTrue($service->active());
        self::assertNull($service->id());
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service name is required.');

        ServiceName::fromString('   ');
    }

    public function testRejectsShortName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service name must be at least 3 characters.');

        ServiceName::fromString('ab');
    }

    public function testRejectsInvalidDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service duration must be greater than zero minutes.');

        ServiceDuration::fromMinutes(0);
    }
}

<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\ServiceAddress;

final class ServiceAddressTest extends TestCase
{
    public function testTrimsValue(): void
    {
        $address = new ServiceAddress('  Av. Providencia 1234  ');

        $this->assertSame('Av. Providencia 1234', $address->value());
    }

    public function testRejectsEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ServiceAddress('');
    }

    /**
     * Nota: por construccion, ServiceAddress SIEMPRE representa un servicio
     * a domicilio (el constructor rechaza valores vacios) -- isDomicilio()
     * siempre da true. El caso "no es a domicilio" se modela con ausencia
     * de ServiceAddress en Bitacora, no con un ServiceAddress vacio. Mismo
     * comportamiento que V1, documentado aqui para que quede explicito.
     */
    public function testIsAlwaysDomicilioByConstruction(): void
    {
        $address = new ServiceAddress('Cualquier direccion');

        $this->assertTrue($address->isDomicilio());
    }

    public function testEqualsComparesByValue(): void
    {
        $a = new ServiceAddress('Av. Kennedy 5000');
        $b = new ServiceAddress('Av. Kennedy 5000');
        $c = new ServiceAddress('Av. Apoquindo 3000');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

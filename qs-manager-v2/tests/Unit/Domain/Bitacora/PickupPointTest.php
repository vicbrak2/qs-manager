<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\PickupPoint;

final class PickupPointTest extends TestCase
{
    public function testTrimsValue(): void
    {
        $point = new PickupPoint('  Portal La Dehesa  ');

        $this->assertSame('Portal La Dehesa', $point->value());
    }

    public function testRejectsEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PickupPoint('   ');
    }

    public function testEqualsComparesByValue(): void
    {
        $a = new PickupPoint('Metro Manquehue');
        $b = new PickupPoint('Metro Manquehue');
        $c = new PickupPoint('Metro Escuela Militar');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

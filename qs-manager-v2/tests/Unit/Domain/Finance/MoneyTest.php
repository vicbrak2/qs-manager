<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Finance;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Finance\Money;

final class MoneyTest extends TestCase
{
    public function testCanBeCreatedFromIntAndReturnsAmount(): void
    {
        $money = Money::fromInt(5000);
        $this->assertSame(5000, $money->amount());
    }

    public function testCanBeAdded(): void
    {
        $m1 = Money::fromInt(100);
        $m2 = Money::fromInt(50);
        
        $result = $m1->add($m2);
        
        $this->assertSame(150, $result->amount());
        $this->assertSame(100, $m1->amount()); // Immutable
    }

    public function testCanBeSubtracted(): void
    {
        $m1 = Money::fromInt(100);
        $m2 = Money::fromInt(50);
        
        $result = $m1->subtract($m2);
        
        $this->assertSame(50, $result->amount());
    }

    public function testCanBeMultipliedByInt(): void
    {
        $m1 = Money::fromInt(100);
        
        $result = $m1->multiply(3);
        
        $this->assertSame(300, $result->amount());
    }

    public function testCanBeCompared(): void
    {
        $m1 = Money::fromInt(100);
        $m2 = Money::fromInt(100);
        $m3 = Money::fromInt(150);
        $m4 = Money::fromInt(50);

        $this->assertTrue($m1->equals($m2));
        $this->assertFalse($m1->equals($m3));

        $this->assertTrue($m3->greaterThan($m1));
        $this->assertFalse($m4->greaterThan($m1));

        $this->assertTrue($m4->lessThan($m1));
        $this->assertFalse($m3->lessThan($m1));
    }
}

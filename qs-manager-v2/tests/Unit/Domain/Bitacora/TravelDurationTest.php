<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\TravelDuration;

final class TravelDurationTest extends TestCase
{
    public function testStoresMinutes(): void
    {
        $duration = new TravelDuration(25);

        $this->assertSame(25, $duration->minutes());
    }

    public function testRejectsNegativeMinutes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TravelDuration(-1);
    }

    public function testAllowsZeroMinutes(): void
    {
        $duration = new TravelDuration(0);

        $this->assertSame(0, $duration->minutes());
    }

    public function testMeetsRecommendedMinimumAt15OrMore(): void
    {
        $this->assertFalse((new TravelDuration(14))->meetsRecommendedMinimum());
        $this->assertTrue((new TravelDuration(15))->meetsRecommendedMinimum());
        $this->assertTrue((new TravelDuration(45))->meetsRecommendedMinimum());
    }

    public function testEqualsComparesByMinutes(): void
    {
        $this->assertTrue((new TravelDuration(20))->equals(new TravelDuration(20)));
        $this->assertFalse((new TravelDuration(20))->equals(new TravelDuration(21)));
    }
}

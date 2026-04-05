<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\ServicesCatalog;

use InvalidArgumentException;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServicePrice;
use QS\Shared\Testing\TestCase;

final class ServicePriceTest extends TestCase
{
    public function testStoresPositivePrice(): void
    {
        $price = new ServicePrice(70000);

        self::assertSame(70000, $price->amount());
    }

    public function testThrowsWhenPriceIsNotPositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service price must be greater than zero.');

        new ServicePrice(0);
    }
}

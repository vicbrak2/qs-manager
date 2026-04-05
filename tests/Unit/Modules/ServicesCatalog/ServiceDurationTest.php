<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\ServicesCatalog;

use InvalidArgumentException;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceDuration;
use QS\Shared\Testing\TestCase;

final class ServiceDurationTest extends TestCase
{
    public function testTotalMinAddsDurationAndBuffer(): void
    {
        $duration = new ServiceDuration(60, 15);

        self::assertSame(60, $duration->durationMin());
        self::assertSame(15, $duration->bufferMin());
        self::assertSame(75, $duration->totalMin());
    }

    public function testThrowsWhenDurationIsNotPositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service duration must be greater than zero.');

        new ServiceDuration(0, 15);
    }

    public function testThrowsWhenBufferIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service buffer can not be negative.');

        new ServiceDuration(60, -1);
    }
}

<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Booking\BookingConflictException;
use RuntimeException;

final class BookingConflictExceptionTest extends TestCase
{
    public function testIsARuntimeException(): void
    {
        $exception = new BookingConflictException('Reserva #123');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testExposesConflictingEvent(): void
    {
        $exception = new BookingConflictException('Reserva #123');

        $this->assertSame('Reserva #123', $exception->getConflictingEvent());
    }

    public function testMessageIncludesConflictingEvent(): void
    {
        $exception = new BookingConflictException('Reserva #123');

        $this->assertStringContainsString('Reserva #123', $exception->getMessage());
        $this->assertStringContainsString('Conflicto de horario', $exception->getMessage());
    }
}

<?php

declare(strict_types=1);

namespace QSManager\Tests\Domain\Booking;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Booking\Booking;
use QSManager\Domain\Booking\BookingId;
use QSManager\Domain\Booking\BookingStatus;

final class BookingTest extends TestCase
{
    public function testCreatesValidBooking(): void
    {
        $scheduledFor = new DateTimeImmutable('2026-07-20T14:30:00Z');
        $booking = Booking::create(
            12,
            34,
            'Juan Perez',
            '+56912345678',
            $scheduledFor,
            'confirmed'
        );

        self::assertSame(12, $booking->serviceId()?->value());
        self::assertSame(34, $booking->staffId()?->value());
        self::assertSame('Juan Perez', $booking->customerName());
        self::assertSame('+56912345678', $booking->customerPhone());
        self::assertSame($scheduledFor, $booking->scheduledFor());
        self::assertSame('confirmed', $booking->status()->value());
        self::assertNull($booking->id());
    }

    public function testHandlesNullValues(): void
    {
        $booking = Booking::create(
            null,
            null,
            null,
            null,
            null,
            'draft'
        );

        self::assertNull($booking->serviceId());
        self::assertNull($booking->staffId());
        self::assertNull($booking->customerName());
        self::assertNull($booking->customerPhone());
        self::assertNull($booking->scheduledFor());
        self::assertSame('draft', $booking->status()->value());
    }

    public function testRejectsInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking status must be one of: draft, confirmed, cancelled, completed.');

        BookingStatus::fromString('invalid_status');
    }

    public function testRejectsInvalidBookingId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking id must be greater than zero.');

        BookingId::fromInt(0);
    }

    public function testRecreatesFromPersistence(): void
    {
        $booking = Booking::fromPersistence(
            99,
            12,
            34,
            ' Camila ',
            ' 98765 ',
            '2026-07-20T14:30:00+00:00',
            'completed',
            ' Maquillaje ',
            ' Camila Villalobos '
        );

        self::assertSame(99, $booking->id()?->value());
        self::assertSame(12, $booking->serviceId()?->value());
        self::assertSame(34, $booking->staffId()?->value());
        self::assertSame('Camila', $booking->customerName()); // Normalized (trimmed)
        self::assertSame('98765', $booking->customerPhone()); // Normalized (trimmed)
        self::assertSame('completed', $booking->status()->value());
        self::assertSame('Maquillaje', $booking->serviceName());
        self::assertSame('Camila Villalobos', $booking->staffName());
    }
}

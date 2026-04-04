<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Booking;

use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Shared\Testing\TestCase;

final class ReservationNormalizerTest extends TestCase
{
    public function testBuildsReservationFromJoinedLatePointRow(): void
    {
        $normalizer = new ReservationNormalizer();
        $reservation = $normalizer->fromRow([
            'id' => 5,
            'order_id' => 9,
            'customer_id' => 14,
            'agent_id' => 7,
            'service_id' => 3,
            'service_name' => 'Combo Social M+P',
            'agent_name' => 'Camila Verdejo',
            'customer_first_name' => 'Ana',
            'customer_last_name' => 'Perez',
            'email' => 'ana@example.com',
            'phone' => '+56911111111',
            'status' => 'approved',
            'price' => 90000,
            'start_date' => '2026-04-03',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'payment_method' => 'transferencia',
            'notes' => 'Cliente VIP',
        ]);

        self::assertSame(5, $reservation->id()->value());
        self::assertSame('Ana Perez', $reservation->clientName());
        self::assertSame('approved', $reservation->status()->value);
    }
}

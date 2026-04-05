<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Booking;

use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Shared\Testing\TestCase;

final class ReservationDTOTest extends TestCase
{
    private ReservationNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new ReservationNormalizer();
    }

    public function testToArrayContainsAllFields(): void
    {
        $reservation = $this->normalizer->fromRow([
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

        $dto = new ReservationDTO($reservation);
        $result = $dto->toArray();

        self::assertSame(5, $result['id']);
        self::assertSame(9, $result['order_id']);
        self::assertSame(14, $result['customer_id']);
        self::assertSame(7, $result['agent_id']);
        self::assertSame(3, $result['service_id']);
        self::assertSame('Combo Social M+P', $result['service_name']);
        self::assertSame('Camila Verdejo', $result['agent_name']);
        self::assertSame('Ana Perez', $result['client_name']);
        self::assertSame('ana@example.com', $result['client_email']);
        self::assertSame('+56911111111', $result['client_phone']);
        self::assertSame('approved', $result['status']);
        self::assertSame(90000, $result['price_clp']);
        self::assertSame('transferencia', $result['payment_method']);
        self::assertSame('Cliente VIP', $result['notes']);
        self::assertIsArray($result['time_range']);
        self::assertSame('2026-04-03', $result['time_range']['date']);
        self::assertSame('09:00:00', $result['time_range']['start_time']);
        self::assertSame('10:30:00', $result['time_range']['end_time']);
    }

    public function testToArrayWithNullableFieldsNull(): void
    {
        $reservation = $this->normalizer->fromRow([
            'id' => 1,
            'start_date' => '2026-04-03',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $result = (new ReservationDTO($reservation))->toArray();

        self::assertNull($result['order_id']);
        self::assertNull($result['agent_name']);
        self::assertNull($result['client_email']);
        self::assertNull($result['price_clp']);
        self::assertNull($result['payment_method']);
        self::assertNull($result['notes']);
    }
}

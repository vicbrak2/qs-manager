<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Booking;

use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Booking\Domain\ValueObject\ReservationStatus;
use QS\Shared\Testing\TestCase;

final class ReservationNormalizerTest extends TestCase
{
    private ReservationNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new ReservationNormalizer();
    }

    public function testBuildsReservationFromCompleteRow(): void
    {
        $reservation = $this->normalizer->fromRow($this->completeRow());

        self::assertSame(5, $reservation->id()->value());
        self::assertSame(9, $reservation->orderId());
        self::assertSame(14, $reservation->customerId());
        self::assertSame(7, $reservation->agentId());
        self::assertSame(3, $reservation->serviceId());
        self::assertSame('Combo Social M+P', $reservation->serviceName());
        self::assertSame('Camila Verdejo', $reservation->agentName());
        self::assertSame('Ana Perez', $reservation->clientName());
        self::assertSame('ana@example.com', $reservation->clientEmail());
        self::assertSame('+56911111111', $reservation->clientPhone());
        self::assertSame(ReservationStatus::Approved, $reservation->status());
        self::assertSame(90000, $reservation->priceClp());
        self::assertSame('transferencia', $reservation->paymentMethod());
        self::assertSame('Cliente VIP', $reservation->notes());
        self::assertSame('2026-04-03', $reservation->timeRange()->serviceDate());
        self::assertSame('09:00:00', $reservation->timeRange()->startTime());
        self::assertSame('10:30:00', $reservation->timeRange()->endTime());
    }

    public function testCombinesFirstAndLastName(): void
    {
        $row = $this->completeRow(['customer_first_name' => 'Maria', 'customer_last_name' => 'Lopez']);
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame('Maria Lopez', $reservation->clientName());
    }

    public function testUsesFirstNameOnlyWhenLastNameMissing(): void
    {
        $row = $this->completeRow(['customer_first_name' => 'Maria', 'customer_last_name' => '']);
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame('Maria', $reservation->clientName());
    }

    public function testFallsBackToDefaultClientNameWhenBothNamesEmpty(): void
    {
        $row = $this->completeRow([
            'customer_first_name' => '',
            'customer_last_name' => '',
        ]);
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame('Clienta sin nombre', $reservation->clientName());
    }

    public function testFallsBackToDefaultClientNameWhenNamesAbsent(): void
    {
        $row = $this->completeRow();
        unset($row['customer_first_name'], $row['customer_last_name'], $row['first_name'], $row['last_name']);

        $reservation = $this->normalizer->fromRow($row);

        self::assertSame('Clienta sin nombre', $reservation->clientName());
    }

    public function testTrimsWhitespaceFromClientName(): void
    {
        $row = $this->completeRow(['customer_first_name' => '  Ana  ', 'customer_last_name' => '  Perez  ']);
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame('Ana     Perez', $reservation->clientName());
    }

    public function testUsesLegacyFirstNameKeys(): void
    {
        $row = $this->completeRow();
        unset($row['customer_first_name'], $row['customer_last_name']);
        $row['first_name'] = 'Carmen';
        $row['last_name'] = 'Soto';

        $reservation = $this->normalizer->fromRow($row);

        self::assertSame('Carmen Soto', $reservation->clientName());
    }

    public function testPrioritizesCustomerFirstNameOverFirstName(): void
    {
        $row = $this->completeRow([
            'customer_first_name' => 'Ana',
            'first_name' => 'Carmen',
            'customer_last_name' => 'Perez',
            'last_name' => 'Soto',
        ]);

        self::assertSame('Ana Perez', $this->normalizer->fromRow($row)->clientName());
    }

    public function testPrioritizesCustomerLastNameOverLastName(): void
    {
        $row = $this->completeRow([
            'customer_first_name' => 'Ana',
            'customer_last_name' => 'Perez',
            'last_name' => 'Soto',
        ]);

        self::assertSame('Ana Perez', $this->normalizer->fromRow($row)->clientName());
    }

    public function testFallsBackToFirstNameWhenCustomerFirstNameAbsent(): void
    {
        $row = $this->completeRow();
        unset($row['customer_first_name']);
        $row['first_name'] = 'Carmen';

        self::assertSame('Carmen Perez', $this->normalizer->fromRow($row)->clientName());
    }

    public function testFallsBackToLastNameWhenCustomerLastNameAbsent(): void
    {
        $row = $this->completeRow();
        unset($row['customer_last_name']);
        $row['last_name'] = 'Soto';

        self::assertSame('Ana Soto', $this->normalizer->fromRow($row)->clientName());
    }

    public function testNullsWhenOptionalFieldsAbsent(): void
    {
        $row = [
            'id' => 1,
            'start_date' => '2026-04-03',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ];

        $reservation = $this->normalizer->fromRow($row);

        self::assertNull($reservation->orderId());
        self::assertNull($reservation->customerId());
        self::assertNull($reservation->agentId());
        self::assertNull($reservation->serviceId());
        self::assertNull($reservation->agentName());
        self::assertNull($reservation->clientEmail());
        self::assertNull($reservation->clientPhone());
        self::assertNull($reservation->priceClp());
        self::assertNull($reservation->paymentMethod());
        self::assertNull($reservation->notes());
    }

    public function testDefaultServiceNameWhenAbsent(): void
    {
        $row = [
            'id' => 1,
            'start_date' => '2026-04-03',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ];
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame('Servicio sin nombre', $reservation->serviceName());
    }

    public function testPendingStatus(): void
    {
        $row = $this->completeRow(['status' => 'pending']);

        self::assertSame(ReservationStatus::Pending, $this->normalizer->fromRow($row)->status());
    }

    public function testCancelledStatus(): void
    {
        $row = $this->completeRow(['status' => 'cancelled']);

        self::assertSame(ReservationStatus::Cancelled, $this->normalizer->fromRow($row)->status());
    }

    public function testUnknownStatusFallsBackToPending(): void
    {
        $row = $this->completeRow(['status' => 'unknown_value']);
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame(ReservationStatus::Pending, $reservation->status());
    }

    public function testNullStatusFallsBackToPending(): void
    {
        $row = $this->completeRow();
        unset($row['status']);
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame(ReservationStatus::Pending, $reservation->status());
    }

    public function testTimeRangeFallbacksWhenDatesAbsent(): void
    {
        $row = ['id' => 1];
        $reservation = $this->normalizer->fromRow($row);

        self::assertSame(gmdate('Y-m-d'), $reservation->timeRange()->serviceDate());
        self::assertSame('00:00:00', $reservation->timeRange()->startTime());
        self::assertSame('00:00:00', $reservation->timeRange()->endTime());
    }

    public function testCastsIdFromStringToInt(): void
    {
        $row = $this->completeRow(['id' => '42']);

        self::assertSame(42, $this->normalizer->fromRow($row)->id()->value());
    }

    public function testCastsOrderIdFromStringToInt(): void
    {
        $row = $this->completeRow(['order_id' => '9']);

        self::assertSame(9, $this->normalizer->fromRow($row)->orderId());
    }

    public function testCastsCustomerIdFromStringToInt(): void
    {
        $row = $this->completeRow(['customer_id' => '14']);

        self::assertSame(14, $this->normalizer->fromRow($row)->customerId());
    }

    public function testCastsAgentIdFromStringToInt(): void
    {
        $row = $this->completeRow(['agent_id' => '7']);

        self::assertSame(7, $this->normalizer->fromRow($row)->agentId());
    }

    public function testCastsServiceIdFromStringToInt(): void
    {
        $row = $this->completeRow(['service_id' => '3']);

        self::assertSame(3, $this->normalizer->fromRow($row)->serviceId());
    }

    public function testCastsPriceFromStringToInt(): void
    {
        $row = $this->completeRow(['price' => '90000']);

        self::assertSame(90000, $this->normalizer->fromRow($row)->priceClp());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function completeRow(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }
}

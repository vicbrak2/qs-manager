<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use PHPUnit\Framework\MockObject\MockObject;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Finance\Application\Query\GetServiceMargin;
use QS\Modules\Finance\Application\QueryHandler\GetServiceMarginHandler;
use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;
use QS\Modules\Finance\Domain\Service\MarginCalculator;
use QS\Shared\Testing\TestCase;

final class GetServiceMarginHandlerTest extends TestCase
{
    /** @var ReservationRepository&MockObject */
    private ReservationRepository $reservationRepository;

    /** @var ServiceCostRepository&MockObject */
    private ServiceCostRepository $serviceCostRepository;

    private ReservationNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->serviceCostRepository = $this->createMock(ServiceCostRepository::class);
        $this->normalizer = new ReservationNormalizer();
    }

    public function testReturnsEmptyWhenNoReservationsForMonth(): void
    {
        $this->reservationRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);
        $this->serviceCostRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        self::assertSame([], $this->handler()->handle(new GetServiceMargin('2026-04')));
    }

    public function testSkipsCancelledReservations(): void
    {
        $cancelled = $this->reservation([
            'status' => 'cancelled',
            'start_date' => '2026-04-03',
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$cancelled]);
        $this->serviceCostRepository->method('findAll')->willReturn([]);

        self::assertSame([], $this->handler()->handle(new GetServiceMargin('2026-04')));
    }

    public function testSkipsReservationsOutsideMonth(): void
    {
        $outsideMonth = $this->reservation([
            'start_date' => '2026-03-15',
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$outsideMonth]);
        $this->serviceCostRepository->method('findAll')->willReturn([]);

        self::assertSame([], $this->handler()->handle(new GetServiceMargin('2026-04')));
    }

    public function testCalculatesMarginWhenCostIsKnown(): void
    {
        $reservation = $this->reservation([
            'service_name' => 'Maquillaje Social',
            'price' => 70000,
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$reservation]);
        $this->serviceCostRepository->method('findAll')->willReturn([
            'maquillaje social' => 40000,
        ]);

        $result = $this->handler()->handle(new GetServiceMargin('2026-04'));

        self::assertCount(1, $result);
        self::assertSame([
            'service_name' => 'Maquillaje Social',
            'reservation_count' => 1,
            'revenue_clp' => 70000,
            'staff_cost_clp' => 40000,
            'margin_clp' => 30000,
            'calculable' => true,
            'warning' => null,
        ], $result[0]->toArray());
    }

    public function testReturnsWarningWhenCostIsUnknown(): void
    {
        $reservation = $this->reservation([
            'service_name' => 'Servicio Nuevo',
            'price' => 80000,
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$reservation]);
        $this->serviceCostRepository->method('findAll')->willReturn([]);

        $result = $this->handler()->handle(new GetServiceMargin('2026-04'));

        self::assertCount(1, $result);
        self::assertSame([
            'service_name' => 'Servicio Nuevo',
            'reservation_count' => 1,
            'revenue_clp' => 80000,
            'staff_cost_clp' => null,
            'margin_clp' => null,
            'calculable' => false,
            'warning' => 'A margin can not be calculated without staff cost.',
        ], $result[0]->toArray());
    }

    public function testAccumulatesReservationsOfSameService(): void
    {
        $first = $this->reservation([
            'id' => 1,
            'service_name' => 'Combo Social M+P',
            'price' => 90000,
            'start_date' => '2026-04-03',
        ]);
        $second = $this->reservation([
            'id' => 2,
            'service_name' => 'Combo Social M+P',
            'price' => 90000,
            'start_date' => '2026-04-10',
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$first, $second]);
        $this->serviceCostRepository->method('findAll')->willReturn([
            'combo social m+p' => 60000,
        ]);

        $result = $this->handler()->handle(new GetServiceMargin('2026-04'));

        self::assertCount(1, $result);
        self::assertSame([
            'service_name' => 'Combo Social M+P',
            'reservation_count' => 2,
            'revenue_clp' => 180000,
            'staff_cost_clp' => 120000,
            'margin_clp' => 60000,
            'calculable' => true,
            'warning' => null,
        ], $result[0]->toArray());
    }

    public function testNormalizesServiceNameForCostLookupAndSortsResults(): void
    {
        $first = $this->reservation([
            'id' => 1,
            'service_name' => '  Maquillaje   Social  ',
        ]);
        $second = $this->reservation([
            'id' => 2,
            'service_name' => 'Combo Social M+P',
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$second, $first]);
        $this->serviceCostRepository->method('findAll')->willReturn([
            'maquillaje social' => 40000,
            'combo social m+p' => 60000,
        ]);

        $result = $this->handler()->handle(new GetServiceMargin('2026-04'));

        self::assertCount(2, $result);
        self::assertSame('Combo Social M+P', $result[0]->toArray()['service_name']);
        self::assertSame('  Maquillaje   Social  ', $result[1]->toArray()['service_name']);
        self::assertSame(30000, $result[1]->toArray()['margin_clp']);
    }

    private function handler(): GetServiceMarginHandler
    {
        return new GetServiceMarginHandler(
            $this->reservationRepository,
            $this->serviceCostRepository,
            new MarginCalculator()
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function reservation(array $overrides = []): \QS\Modules\Booking\Domain\Entity\Reservation
    {
        return $this->normalizer->fromRow(array_merge([
            'id' => 1,
            'service_name' => 'Maquillaje Social',
            'customer_first_name' => 'Ana',
            'customer_last_name' => 'Perez',
            'status' => 'approved',
            'price' => 70000,
            'start_date' => '2026-04-03',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ], $overrides));
    }
}

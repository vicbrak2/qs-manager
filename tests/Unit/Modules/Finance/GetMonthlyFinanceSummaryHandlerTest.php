<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Finance\Application\Query\GetMonthlyFinanceSummary;
use QS\Modules\Finance\Application\QueryHandler\GetMonthlyFinanceSummaryHandler;
use QS\Modules\Finance\Domain\Entity\Expense;
use QS\Modules\Finance\Domain\Entity\Payment;
use QS\Modules\Finance\Domain\Repository\ExpenseRepository;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;
use QS\Modules\Finance\Domain\Service\MonthlySummaryBuilder;
use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;
use QS\Shared\Testing\TestCase;
use QS\Shared\ValueObjects\Money;

final class GetMonthlyFinanceSummaryHandlerTest extends TestCase
{
    /** @var ReservationRepository&MockObject */
    private ReservationRepository $reservationRepository;

    /** @var PaymentRepository&MockObject */
    private PaymentRepository $paymentRepository;

    /** @var ExpenseRepository&MockObject */
    private ExpenseRepository $expenseRepository;

    /** @var ServiceCostRepository&MockObject */
    private ServiceCostRepository $serviceCostRepository;

    private ReservationNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->expenseRepository = $this->createMock(ExpenseRepository::class);
        $this->serviceCostRepository = $this->createMock(ServiceCostRepository::class);
        $this->normalizer = new ReservationNormalizer();
    }

    public function testReturnsZeroSummaryWhenNoDataExists(): void
    {
        $this->reservationRepository->method('findAll')->willReturn([]);
        $this->paymentRepository->expects(self::once())
            ->method('findByMonth')
            ->with('2026-04')
            ->willReturn([]);
        $this->expenseRepository->expects(self::once())
            ->method('findByMonth')
            ->with('2026-04')
            ->willReturn([]);
        $this->serviceCostRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->handler()->handle(new GetMonthlyFinanceSummary('2026-04'))->toArray();

        self::assertSame([
            'month' => '2026-04',
            'booked_revenue_clp' => 0,
            'paid_revenue_clp' => 0,
            'expenses_clp' => 0,
            'staff_cost_clp' => 0,
            'projected_margin_clp' => 0,
            'realized_margin_clp' => 0,
            'service_count' => 0,
            'payment_count' => 0,
            'expense_count' => 0,
            'missing_cost_services_count' => 0,
        ], $result);
    }

    public function testExcludesCancelledReservationsAndOtherMonths(): void
    {
        $included = $this->reservation([
            'id' => 1,
            'price' => 90000,
            'status' => 'approved',
            'start_date' => '2026-04-03',
        ]);
        $cancelled = $this->reservation([
            'id' => 2,
            'price' => 90000,
            'status' => 'cancelled',
            'start_date' => '2026-04-03',
        ]);
        $outsideMonth = $this->reservation([
            'id' => 3,
            'price' => 90000,
            'status' => 'approved',
            'start_date' => '2026-03-20',
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$included, $cancelled, $outsideMonth]);
        $this->paymentRepository->method('findByMonth')->willReturn([]);
        $this->expenseRepository->method('findByMonth')->willReturn([]);
        $this->serviceCostRepository->method('findAll')->willReturn([]);

        $result = $this->handler()->handle(new GetMonthlyFinanceSummary('2026-04'))->toArray();

        self::assertSame(90000, $result['booked_revenue_clp']);
        self::assertSame(1, $result['service_count']);
    }

    public function testAccumulatesRevenueStaffCostPaymentsAndExpenses(): void
    {
        $first = $this->reservation([
            'id' => 1,
            'service_name' => 'Maquillaje Social',
            'price' => 70000,
            'start_date' => '2026-04-03',
        ]);
        $second = $this->reservation([
            'id' => 2,
            'service_name' => '  Maquillaje   Social  ',
            'price' => 70000,
            'start_date' => '2026-04-10',
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$first, $second]);
        $this->paymentRepository->method('findByMonth')->willReturn([
            $this->payment(1, 90000, '2026-04'),
            $this->payment(2, 50000, '2026-04'),
        ]);
        $this->expenseRepository->method('findByMonth')->willReturn([
            $this->expense(1, 30000, '2026-04'),
        ]);
        $this->serviceCostRepository->method('findAll')->willReturn([
            'maquillaje social' => 40000,
        ]);

        $result = $this->handler()->handle(new GetMonthlyFinanceSummary('2026-04'))->toArray();

        self::assertSame(140000, $result['booked_revenue_clp']);
        self::assertSame(140000, $result['paid_revenue_clp']);
        self::assertSame(30000, $result['expenses_clp']);
        self::assertSame(80000, $result['staff_cost_clp']);
        self::assertSame(30000, $result['projected_margin_clp']);
        self::assertSame(30000, $result['realized_margin_clp']);
        self::assertSame(2, $result['service_count']);
        self::assertSame(2, $result['payment_count']);
        self::assertSame(1, $result['expense_count']);
        self::assertSame(0, $result['missing_cost_services_count']);
    }

    public function testCountsServicesWithMissingCost(): void
    {
        $knownCost = $this->reservation([
            'id' => 1,
            'service_name' => 'Maquillaje Social',
            'price' => 70000,
        ]);
        $missingCost = $this->reservation([
            'id' => 2,
            'service_name' => 'Servicio Sin Costo',
            'price' => 50000,
        ]);

        $this->reservationRepository->method('findAll')->willReturn([$knownCost, $missingCost]);
        $this->paymentRepository->method('findByMonth')->willReturn([]);
        $this->expenseRepository->method('findByMonth')->willReturn([]);
        $this->serviceCostRepository->method('findAll')->willReturn([
            'maquillaje social' => 40000,
        ]);

        $result = $this->handler()->handle(new GetMonthlyFinanceSummary('2026-04'))->toArray();

        self::assertSame(120000, $result['booked_revenue_clp']);
        self::assertSame(40000, $result['staff_cost_clp']);
        self::assertSame(1, $result['missing_cost_services_count']);
        self::assertSame(80000, $result['projected_margin_clp']);
    }

    private function handler(): GetMonthlyFinanceSummaryHandler
    {
        return new GetMonthlyFinanceSummaryHandler(
            $this->reservationRepository,
            $this->paymentRepository,
            $this->expenseRepository,
            $this->serviceCostRepository,
            new MonthlySummaryBuilder()
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

    private function payment(int $id, int $amountClp, string $closingMonth): Payment
    {
        return new Payment(
            $id,
            $id,
            sprintf('Pago %d', $id),
            new Money($amountClp),
            PaymentMethod::Transferencia,
            'registered',
            new DateTimeImmutable('2026-04-03T10:00:00+00:00'),
            $closingMonth
        );
    }

    private function expense(int $id, int $amountClp, string $month): Expense
    {
        return new Expense(
            $id,
            sprintf('Gasto %d', $id),
            new Money($amountClp),
            'variable',
            $month,
            null,
            new DateTimeImmutable('2026-04-01T09:00:00+00:00')
        );
    }
}

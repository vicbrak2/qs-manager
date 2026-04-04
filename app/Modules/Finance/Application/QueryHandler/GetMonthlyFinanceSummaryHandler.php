<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\QueryHandler;

use QS\Modules\Booking\Domain\Entity\Reservation;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Finance\Application\DTO\MonthlySummaryDTO;
use QS\Modules\Finance\Application\Query\GetMonthlyFinanceSummary;
use QS\Modules\Finance\Domain\Repository\ExpenseRepository;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;
use QS\Modules\Finance\Domain\Service\MonthlySummaryBuilder;

final class GetMonthlyFinanceSummaryHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly ExpenseRepository $expenseRepository,
        private readonly ServiceCostRepository $serviceCostRepository,
        private readonly MonthlySummaryBuilder $monthlySummaryBuilder
    ) {
    }

    public function handle(GetMonthlyFinanceSummary $query): MonthlySummaryDTO
    {
        $reservations = $this->reservationsForMonth($query->month);
        $payments = $this->paymentRepository->findByMonth($query->month);
        $expenses = $this->expenseRepository->findByMonth($query->month);
        $costMap = $this->serviceCostRepository->findAll();
        $bookedRevenueClp = 0;
        $staffCostClp = 0;
        $missingCostServicesCount = 0;

        foreach ($reservations as $reservation) {
            $bookedRevenueClp += $reservation->priceClp() ?? 0;
            $serviceCost = $costMap[$this->normalizeServiceName($reservation->serviceName())] ?? null;

            if ($serviceCost === null) {
                ++$missingCostServicesCount;
                continue;
            }

            $staffCostClp += $serviceCost;
        }

        $paidRevenueClp = array_sum(array_map(
            static fn ($payment): int => $payment->amount()->amount(),
            $payments
        ));
        $expensesClp = array_sum(array_map(
            static fn ($expense): int => $expense->amount()->amount(),
            $expenses
        ));

        return new MonthlySummaryDTO(
            $this->monthlySummaryBuilder->build(
                $query->month,
                $bookedRevenueClp,
                $paidRevenueClp,
                $expensesClp,
                $staffCostClp,
                count($reservations),
                count($payments),
                count($expenses),
                $missingCostServicesCount
            )
        );
    }

    /**
     * @return array<int, Reservation>
     */
    private function reservationsForMonth(string $month): array
    {
        return array_values(array_filter(
            $this->reservationRepository->findAll(),
            static fn (Reservation $reservation): bool => str_starts_with($reservation->timeRange()->serviceDate(), $month)
                && $reservation->status()->value !== 'cancelled'
        ));
    }

    private function normalizeServiceName(string $serviceName): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $serviceName) ?? $serviceName));
    }
}

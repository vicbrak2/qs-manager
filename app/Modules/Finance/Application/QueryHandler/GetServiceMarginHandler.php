<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\QueryHandler;

use InvalidArgumentException;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Finance\Application\DTO\ServiceMarginDTO;
use QS\Modules\Finance\Application\Query\GetServiceMargin;
use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;
use QS\Modules\Finance\Domain\Service\MarginCalculator;

final class GetServiceMarginHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ServiceCostRepository $serviceCostRepository,
        private readonly MarginCalculator $marginCalculator
    ) {
    }

    /**
     * @return array<int, ServiceMarginDTO>
     */
    public function handle(GetServiceMargin $query): array
    {
        $costMap = $this->serviceCostRepository->findAll();
        $grouped = [];

        foreach ($this->reservationRepository->findAll() as $reservation) {
            if (! str_starts_with($reservation->timeRange()->serviceDate(), $query->month) || $reservation->status()->value === 'cancelled') {
                continue;
            }

            $serviceName = $reservation->serviceName();
            $serviceKey = $this->normalizeServiceName($serviceName);
            $grouped[$serviceKey] ??= [
                'service_name' => $serviceName,
                'reservation_count' => 0,
                'revenue_clp' => 0,
                'staff_cost_clp' => $costMap[$serviceKey] ?? null,
            ];
            ++$grouped[$serviceKey]['reservation_count'];
            $grouped[$serviceKey]['revenue_clp'] += $reservation->priceClp() ?? 0;
        }

        ksort($grouped);

        return array_map(function (array $item): ServiceMarginDTO {
            $staffCostClp = is_int($item['staff_cost_clp']) ? $item['staff_cost_clp'] * $item['reservation_count'] : null;

            try {
                $marginClp = $this->marginCalculator->calculate($item['revenue_clp'], $staffCostClp);

                return new ServiceMarginDTO(
                    $item['service_name'],
                    $item['reservation_count'],
                    $item['revenue_clp'],
                    $staffCostClp,
                    $marginClp,
                    true
                );
            } catch (InvalidArgumentException $exception) {
                return new ServiceMarginDTO(
                    $item['service_name'],
                    $item['reservation_count'],
                    $item['revenue_clp'],
                    $staffCostClp,
                    null,
                    false,
                    $exception->getMessage()
                );
            }
        }, array_values($grouped));
    }

    private function normalizeServiceName(string $serviceName): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $serviceName) ?? $serviceName));
    }
}

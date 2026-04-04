<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\DTO;

final class ServiceMarginDTO
{
    public function __construct(
        private readonly string $serviceName,
        private readonly int $reservationCount,
        private readonly int $revenueClp,
        private readonly ?int $staffCostClp,
        private readonly ?int $marginClp,
        private readonly bool $calculable,
        private readonly ?string $warning = null
    ) {
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'service_name' => $this->serviceName,
            'reservation_count' => $this->reservationCount,
            'revenue_clp' => $this->revenueClp,
            'staff_cost_clp' => $this->staffCostClp,
            'margin_clp' => $this->marginClp,
            'calculable' => $this->calculable,
            'warning' => $this->warning,
        ];
    }
}

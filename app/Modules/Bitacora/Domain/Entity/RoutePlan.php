<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Domain\Entity;

use QS\Modules\Bitacora\Domain\ValueObject\PickupPoint;
use QS\Modules\Bitacora\Domain\ValueObject\TravelDuration;

final class RoutePlan
{
    public function __construct(
        private readonly PickupPoint $pickupPoint,
        private readonly ?string $pickupOrder,
        private readonly TravelDuration $travelDuration,
        private readonly ?string $arrivalTime
    ) {
    }

    public function pickupPoint(): PickupPoint
    {
        return $this->pickupPoint;
    }

    public function pickupOrder(): ?string
    {
        return $this->pickupOrder;
    }

    public function travelDuration(): TravelDuration
    {
        return $this->travelDuration;
    }

    public function arrivalTime(): ?string
    {
        return $this->arrivalTime;
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'pickup_point' => $this->pickupPoint->value(),
            'pickup_order' => $this->pickupOrder,
            'travel_duration_min' => $this->travelDuration->minutes(),
            'recommended_minimum_met' => $this->travelDuration->meetsRecommendedMinimum(),
            'arrival_time' => $this->arrivalTime,
        ];
    }
}

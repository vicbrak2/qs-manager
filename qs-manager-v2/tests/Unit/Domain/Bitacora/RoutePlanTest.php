<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\PickupPoint;
use QSManager\Domain\Bitacora\RoutePlan;
use QSManager\Domain\Bitacora\TravelDuration;

final class RoutePlanTest extends TestCase
{
    public function testExposesAllFields(): void
    {
        $plan = new RoutePlan(
            new PickupPoint('Metro Manquehue'),
            'primero',
            new TravelDuration(20),
            '09:30'
        );

        $this->assertSame('Metro Manquehue', $plan->pickupPoint()->value());
        $this->assertSame('primero', $plan->pickupOrder());
        $this->assertSame(20, $plan->travelDuration()->minutes());
        $this->assertSame('09:30', $plan->arrivalTime());
    }

    public function testAllowsNullPickupOrderAndArrivalTime(): void
    {
        $plan = new RoutePlan(new PickupPoint('Metro Manquehue'), null, new TravelDuration(10), null);

        $this->assertNull($plan->pickupOrder());
        $this->assertNull($plan->arrivalTime());
    }

    public function testToArrayIncludesRecommendedMinimumFlag(): void
    {
        $plan = new RoutePlan(new PickupPoint('Metro Manquehue'), null, new TravelDuration(5), null);

        $array = $plan->toArray();

        $this->assertSame('Metro Manquehue', $array['pickup_point']);
        $this->assertSame(5, $array['travel_duration_min']);
        $this->assertFalse($array['recommended_minimum_met']);
    }
}

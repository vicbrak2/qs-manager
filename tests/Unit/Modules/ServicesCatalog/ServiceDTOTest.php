<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\ServicesCatalog;

use QS\Modules\ServicesCatalog\Application\DTO\ServiceDTO;
use QS\Modules\ServicesCatalog\Domain\Entity\Service;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceCategory;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceDuration;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServicePrice;
use QS\Modules\ServicesCatalog\Domain\ValueObject\StaffRequirement;
use QS\Shared\Testing\TestCase;
use QS\Shared\ValueObjects\ServiceId;

final class ServiceDTOTest extends TestCase
{
    public function testToArrayExposesServiceFields(): void
    {
        $payload = (new ServiceDTO($this->service()))->toArray();

        self::assertSame(3, $payload['id']);
        self::assertSame('Maquillaje Social', $payload['name']);
        self::assertSame('maquillaje', $payload['category']);
        self::assertSame(60, $payload['duration_min']);
        self::assertSame(15, $payload['buffer_min']);
        self::assertSame(75, $payload['total_min']);
        self::assertSame(70000, $payload['price_clp']);
        self::assertSame(40000, $payload['staff_cost_clp']);
        self::assertSame('mua', $payload['staff_required']);
        self::assertTrue($payload['active']);
        self::assertSame('Servicio social individual.', $payload['description']);
        self::assertFalse($payload['has_staff_cost_warning']);
    }

    public function testFlagsWarningWhenStaffCostExceedsPrice(): void
    {
        $payload = (new ServiceDTO($this->service(staffCostClp: 90000)))->toArray();

        self::assertTrue($payload['has_staff_cost_warning']);
    }

    private function service(int $staffCostClp = 40000): Service
    {
        return new Service(
            new ServiceId(3),
            'Maquillaje Social',
            ServiceCategory::Maquillaje,
            new ServiceDuration(60, 15),
            new ServicePrice(70000),
            $staffCostClp,
            StaffRequirement::Mua,
            true,
            'Servicio social individual.'
        );
    }
}

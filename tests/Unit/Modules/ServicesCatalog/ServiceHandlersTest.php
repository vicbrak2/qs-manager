<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\ServicesCatalog;

use PHPUnit\Framework\MockObject\MockObject;
use QS\Modules\ServicesCatalog\Application\DTO\ServiceDTO;
use QS\Modules\ServicesCatalog\Application\Query\GetAllServices;
use QS\Modules\ServicesCatalog\Application\Query\GetServiceById;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetAllServicesHandler;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetServiceByIdHandler;
use QS\Modules\ServicesCatalog\Domain\Entity\Service;
use QS\Modules\ServicesCatalog\Domain\Repository\ServiceRepository;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceCategory;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceDuration;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServicePrice;
use QS\Modules\ServicesCatalog\Domain\ValueObject\StaffRequirement;
use QS\Shared\Testing\TestCase;
use QS\Shared\ValueObjects\ServiceId;

final class ServiceHandlersTest extends TestCase
{
    /** @var ServiceRepository&MockObject */
    private ServiceRepository $serviceRepository;

    protected function setUp(): void
    {
        $this->serviceRepository = $this->createMock(ServiceRepository::class);
    }

    public function testGetAllServicesHandlerReturnsDtos(): void
    {
        $this->serviceRepository->expects(self::once())
            ->method('findAll')
            ->with(true)
            ->willReturn([$this->service(1), $this->service(2)]);

        $result = (new GetAllServicesHandler($this->serviceRepository))->handle(new GetAllServices());

        self::assertCount(2, $result);
        self::assertInstanceOf(ServiceDTO::class, $result[0]);
        self::assertSame(2, $result[1]->toArray()['id']);
    }

    public function testGetServiceByIdHandlerReturnsNullWhenMissing(): void
    {
        $this->serviceRepository->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        self::assertNull((new GetServiceByIdHandler($this->serviceRepository))->handle(new GetServiceById(99)));
    }

    public function testGetServiceByIdHandlerReturnsDtoWhenFound(): void
    {
        $this->serviceRepository->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($this->service(7));

        $result = (new GetServiceByIdHandler($this->serviceRepository))->handle(new GetServiceById(7));

        self::assertInstanceOf(ServiceDTO::class, $result);
        self::assertSame(7, $result->toArray()['id']);
    }

    private function service(int $id): Service
    {
        return new Service(
            new ServiceId($id),
            'Servicio ' . $id,
            ServiceCategory::Combo,
            new ServiceDuration(90, 15),
            new ServicePrice(90000),
            60000,
            StaffRequirement::Ambos,
            true,
            null
        );
    }
}

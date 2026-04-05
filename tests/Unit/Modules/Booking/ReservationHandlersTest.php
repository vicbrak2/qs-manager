<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Booking;

use PHPUnit\Framework\MockObject\MockObject;
use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetAllReservations;
use QS\Modules\Booking\Application\Query\GetMuaAgenda;
use QS\Modules\Booking\Application\Query\GetReservationById;
use QS\Modules\Booking\Application\Query\GetTodayReservations;
use QS\Modules\Booking\Application\QueryHandler\GetAllReservationsHandler;
use QS\Modules\Booking\Application\QueryHandler\GetMuaAgendaHandler;
use QS\Modules\Booking\Application\QueryHandler\GetReservationByIdHandler;
use QS\Modules\Booking\Application\QueryHandler\GetTodayReservationsHandler;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Shared\Testing\TestCase;

final class ReservationHandlersTest extends TestCase
{
    /** @var ReservationRepository&MockObject */
    private ReservationRepository $repository;
    private ReservationNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new ReservationNormalizer();
        $this->repository = $this->createMock(ReservationRepository::class);
    }

    public function testTodayHandlerReturnsDTOs(): void
    {
        $reservation = $this->normalizer->fromRow($this->baseRow(['id' => 1]));

        $this->repository
            ->expects(self::once())
            ->method('findToday')
            ->willReturn([$reservation]);

        $handler = new GetTodayReservationsHandler($this->repository);
        $result = $handler->handle(new GetTodayReservations());

        self::assertCount(1, $result);
        self::assertInstanceOf(ReservationDTO::class, $result[0]);
        self::assertSame(1, $result[0]->toArray()['id']);
    }

    public function testTodayHandlerReturnsEmptyArrayWhenNoReservations(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findToday')
            ->willReturn([]);

        $handler = new GetTodayReservationsHandler($this->repository);
        $result = $handler->handle(new GetTodayReservations());

        self::assertSame([], $result);
    }

    public function testAllHandlerReturnsDTOs(): void
    {
        $first = $this->normalizer->fromRow($this->baseRow(['id' => 1]));
        $second = $this->normalizer->fromRow($this->baseRow(['id' => 2]));

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn([$first, $second]);

        $handler = new GetAllReservationsHandler($this->repository);
        $result = $handler->handle(new GetAllReservations());

        self::assertCount(2, $result);
        self::assertSame(2, $result[1]->toArray()['id']);
    }

    public function testByIdHandlerReturnsDTOWhenFound(): void
    {
        $reservation = $this->normalizer->fromRow($this->baseRow(['id' => 5]));

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($reservation);

        $handler = new GetReservationByIdHandler($this->repository);
        $result = $handler->handle(new GetReservationById(5));

        self::assertInstanceOf(ReservationDTO::class, $result);
        self::assertSame(5, $result->toArray()['id']);
    }

    public function testByIdHandlerReturnsNullWhenNotFound(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $handler = new GetReservationByIdHandler($this->repository);
        $result = $handler->handle(new GetReservationById(99));

        self::assertNull($result);
    }

    public function testMuaAgendaHandlerReturnsDTOsForStaff(): void
    {
        $reservation = $this->normalizer->fromRow($this->baseRow(['id' => 3, 'agent_id' => 7]));

        $this->repository
            ->expects(self::once())
            ->method('findByStaffAndDate')
            ->with(7, '2026-04-03')
            ->willReturn([$reservation]);

        $handler = new GetMuaAgendaHandler($this->repository);
        $result = $handler->handle(new GetMuaAgenda(7, '2026-04-03'));

        self::assertCount(1, $result);
        self::assertSame(7, $result[0]->toArray()['agent_id']);
    }

    public function testMuaAgendaHandlerReturnsEmptyWhenNoResults(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByStaffAndDate')
            ->with(7, '2026-04-03')
            ->willReturn([]);

        $handler = new GetMuaAgendaHandler($this->repository);
        $result = $handler->handle(new GetMuaAgenda(7, '2026-04-03'));

        self::assertSame([], $result);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function baseRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'service_name' => 'Maquillaje Social',
            'customer_first_name' => 'Ana',
            'customer_last_name' => 'Perez',
            'status' => 'approved',
            'start_date' => '2026-04-03',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ], $overrides);
    }
}

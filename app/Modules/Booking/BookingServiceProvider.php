<?php

declare(strict_types=1);

namespace QS\Modules\Booking;

use function DI\autowire;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\Booking\Application\Command\CreateReservation;
use QS\Modules\Booking\Application\CommandHandler\CreateReservationHandler;
use QS\Modules\Booking\Application\Query\GetAllReservations;
use QS\Modules\Booking\Application\Query\GetMuaAgenda;
use QS\Modules\Booking\Application\Query\GetReservationById;
use QS\Modules\Booking\Application\Query\GetTodayReservations;
use QS\Modules\Booking\Application\QueryHandler\GetAllReservationsHandler;
use QS\Modules\Booking\Application\QueryHandler\GetMuaAgendaHandler;
use QS\Modules\Booking\Application\QueryHandler\GetReservationByIdHandler;
use QS\Modules\Booking\Application\QueryHandler\GetTodayReservationsHandler;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Repository\SheetEventRepository;
use QS\Modules\Booking\Domain\Service\CalendarGateway;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Booking\Domain\Service\SheetsSyncGateway;
use QS\Modules\Booking\Infrastructure\N8n\N8nCalendarGateway;
use QS\Modules\Booking\Infrastructure\N8n\N8nSheetsSyncGateway;
use QS\Modules\Booking\Infrastructure\Persistence\WpdbLatepointRepository;
use QS\Modules\Booking\Infrastructure\Persistence\WpdbSheetEventRepository;
use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;
use QS\Modules\Booking\Interfaces\Rest\MuaAgendaController;
use QS\Modules\Booking\Interfaces\Rest\ReservationsController;
use QS\Modules\Booking\Interfaces\Rest\SheetEventsController;
use QS\Modules\Booking\Interfaces\WP\BookingAdminPage;

final class BookingServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            LatepointTableMap::class         => autowire(),
            ReservationNormalizer::class      => autowire(),
            ReservationRepository::class      => autowire(WpdbLatepointRepository::class),
            SheetEventRepository::class       => autowire(WpdbSheetEventRepository::class),
            CalendarGateway::class            => autowire(N8nCalendarGateway::class),
            SheetsSyncGateway::class          => autowire(N8nSheetsSyncGateway::class),
            CreateReservationHandler::class   => autowire(),
            GetAll
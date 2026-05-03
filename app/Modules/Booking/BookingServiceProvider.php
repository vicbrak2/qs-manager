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
use QS\Modules\Booking\Domain\Service\CalendarGateway;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Booking\Infrastructure\N8n\N8nCalendarGateway;
use QS\Modules\Booking\Infrastructure\Persistence\WpdbLatepointRepository;
use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;
use QS\Modules\Booking\Interfaces\Rest\MuaAgendaController;
use QS\Modules\Booking\Interfaces\Rest\ReservationsController;
use QS\Modules\Booking\Interfaces\WP\BookingAdminPage;

final class BookingServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            LatepointTableMap::class => autowire(),
            ReservationNormalizer::class => autowire(),
            ReservationRepository::class => autowire(WpdbLatepointRepository::class),
            CalendarGateway::class => autowire(N8nCalendarGateway::class),
            CreateReservationHandler::class => autowire(),
            GetAllReservationsHandler::class => autowire(),
            GetTodayReservationsHandler::class => autowire(),
            GetReservationByIdHandler::class => autowire(),
            GetMuaAgendaHandler::class => autowire(),
            ReservationsController::class => autowire(),
            MuaAgendaController::class => autowire(),
            BookingAdminPage::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [
            CreateReservation::class => CreateReservationHandler::class,
        ];
    }

    public static function queryHandlers(): array
    {
        return [
            GetAllReservations::class => GetAllReservationsHandler::class,
            GetMuaAgenda::class => GetMuaAgendaHandler::class,
            GetReservationById::class => GetReservationByIdHandler::class,
            GetTodayReservations::class => GetTodayReservationsHandler::class,
        ];
    }

    public static function hookables(): array
    {
        return [
            BookingAdminPage::class,
        ];
    }

    public static function activationHooks(): array
    {
        return [];
    }
}

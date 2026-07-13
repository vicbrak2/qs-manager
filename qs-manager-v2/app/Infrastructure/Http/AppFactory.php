<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Http;

use QSManager\Application\Booking\CreateBooking;
use QSManager\Application\Booking\ListBookings;
use QSManager\Application\Booking\SyncBookingToGas;
use QSManager\Application\ServicesCatalog\CreateService;
use QSManager\Application\ServicesCatalog\ListServices;
use QSManager\Application\Team\CreateStaffMember;
use QSManager\Application\Team\ListStaffMembers;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Gas\GasBookingPayloadMapper;
use QSManager\Infrastructure\Gas\HttpGasBookingGateway;
use QSManager\Infrastructure\Persistence\Postgres\PostgresBookingRepository;
use QSManager\Infrastructure\Persistence\Postgres\PostgresServiceRepository;
use QSManager\Infrastructure\Persistence\Postgres\PostgresStaffRepository;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;
use QSManager\Infrastructure\Stubs\LocalAgentResponder;
use QSManager\Interfaces\Http\BookingController;
use QSManager\Interfaces\Http\HealthController;
use QSManager\Interfaces\Http\ModulesController;
use QSManager\Interfaces\Http\ServicesController;
use QSManager\Interfaces\Http\SheetSyncController;
use QSManager\Interfaces\Http\TeamController;
use QSManager\Interfaces\Http\Validation\BookingRequestValidator;
use QSManager\Interfaces\Http\WebController;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    public static function create(?\PDO $connection = null): App
    {
        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(self::envBool('APP_DEBUG', true), true, true);

        $connection ??= ConnectionFactory::fromEnvironment();
        $agentResponder = new LocalAgentResponder();
        $serviceRepository = new PostgresServiceRepository($connection);
        $staffRepository = new PostgresStaffRepository($connection);
        $bookingRepository = new PostgresBookingRepository($connection);
        $gasGateway = new HttpGasBookingGateway(
            self::envString('GAS_WEBAPP_URL'),
            new GasBookingPayloadMapper(),
        );
        $sheetImporter = new PostgresSheetReplicaImporter(
            $connection,
            new GoogleSheetsCsvReader(),
        );

        (new WebController())->register($app);
        (new HealthController($connection))->register($app);
        (new ModulesController($agentResponder))->register($app);
        (new ServicesController(
            new CreateService($serviceRepository),
            new ListServices($serviceRepository),
            $serviceRepository,
        ))->register($app);
        (new TeamController(
            new CreateStaffMember($staffRepository),
            new ListStaffMembers($staffRepository),
        ))->register($app);
        (new SheetSyncController(
            $sheetImporter,
            $connection,
            self::envBool('SHEETS_READ_SYNC_ENABLED', false),
        ))->register($app);
        (new BookingController(
            new CreateBooking($bookingRepository),
            new ListBookings($bookingRepository),
            new BookingRequestValidator($serviceRepository, $staffRepository),
            new SyncBookingToGas($bookingRepository, $gasGateway),
            $bookingRepository,
        ))->register($app);

        return $app;
    }

    private static function envBool(string $key, bool $default): bool
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function envString(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

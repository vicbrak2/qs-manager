<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Http;

use QSManager\Application\Bitacora\SaveBitacora;
use QSManager\Application\Booking\CreateBooking;
use QSManager\Application\Booking\ListBookings;
use QSManager\Application\Booking\SyncBookingToGas;
use QSManager\Application\ServicesCatalog\CreateService;
use QSManager\Application\ServicesCatalog\ListServices;
use QSManager\Application\Team\CheckStaffAvailability;
use QSManager\Application\Team\DeleteStaffMember;
use QSManager\Application\Team\SaveStaffMember;
use QSManager\Application\Team\ListStaffMembers;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Gas\GasBookingPayloadMapper;
use QSManager\Infrastructure\Gas\HttpGasBookingGateway;
use QSManager\Infrastructure\Gas\HttpGasServiceCatalogGateway;
use QSManager\Domain\Bitacora\BitacoraPolicy;
use QSManager\Domain\Team\AvailabilityChecker;
use QSManager\Infrastructure\Persistence\Postgres\PostgresBitacoraRepository;
use QSManager\Infrastructure\Persistence\Postgres\PostgresBookingOperationalCostReadRepository;
use QSManager\Infrastructure\Persistence\Postgres\PostgresBookingRepository;
use QSManager\Infrastructure\Persistence\Postgres\PostgresServiceRepository;
use QSManager\Infrastructure\Persistence\Postgres\PostgresStaffRepository;
use QSManager\Infrastructure\Sheets\GoogleSheetsCsvReader;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;
use QSManager\Infrastructure\Sheets\PostgresSyncQueue;
use QSManager\Infrastructure\Stubs\LocalAgentResponder;
use QSManager\Infrastructure\Persistence\Postgres\PostgresFinanceReadRepository;
use QSManager\Interfaces\Http\BitacoraController;
use QSManager\Interfaces\Http\BookingController;
use QSManager\Interfaces\Http\FinanceController;
use QSManager\Interfaces\Http\HealthController;
use QSManager\Interfaces\Http\ModulesController;
use QSManager\Interfaces\Http\ServicesController;
use QSManager\Interfaces\Http\SheetSyncController;
use QSManager\Interfaces\Http\TeamController;
use QSManager\Interfaces\Http\Validation\BitacoraRequestValidator;
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
        $syncQueue = new PostgresSyncQueue($connection);
        $catalogGateway = null;
        $catalogUrl = self::envString('GAS_CATALOG_WEBAPP_URL');
        $catalogSecret = self::envString('GAS_CATALOG_SECRET');
        if (self::envBool('SHEETS_WRITE_SYNC_ENABLED', false) && $catalogUrl !== null && $catalogSecret !== null) {
            $catalogGateway = new HttpGasServiceCatalogGateway($catalogUrl, $catalogSecret);
        }

        (new WebController())->register($app);
        (new HealthController($connection))->register($app);
        (new FinanceController(new PostgresFinanceReadRepository($connection)))->register($app);
        (new ModulesController($agentResponder))->register($app);
        (new ServicesController(
            new CreateService($serviceRepository),
            new ListServices($serviceRepository),
            $serviceRepository,
            catalogGateway: $catalogGateway,
            syncQueue: $syncQueue,
        ))->register($app);
        (new TeamController(
            new SaveStaffMember($staffRepository),
            new ListStaffMembers($staffRepository),
            $staffRepository,
            new CheckStaffAvailability($bookingRepository, new AvailabilityChecker()),
            new DeleteStaffMember($staffRepository),
        ))->register($app);
        (new SheetSyncController(
            $syncQueue,
            $connection,
            self::envBool('SHEETS_READ_SYNC_ENABLED', false),
        ))->register($app);
        $bitacoraRepository = new PostgresBitacoraRepository($connection);
        (new BitacoraController(
            new SaveBitacora($bitacoraRepository, $bookingRepository, new BitacoraPolicy()),
            new BitacoraRequestValidator($staffRepository, $bookingRepository),
            $bitacoraRepository,
        ))->register($app);
        (new BookingController(
            new CreateBooking($bookingRepository, $serviceRepository),
            new ListBookings($bookingRepository),
            new BookingRequestValidator($serviceRepository, $staffRepository),
            new SyncBookingToGas($bookingRepository, $gasGateway),
            $bookingRepository,
            new PostgresBookingOperationalCostReadRepository($connection),
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

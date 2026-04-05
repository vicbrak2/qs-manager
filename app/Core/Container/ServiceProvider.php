<?php

declare(strict_types=1);

namespace QS\Core\Container;

use function DI\autowire;

use DI\Container as DiContainer;

use function DI\factory;
use function DI\get;

use Psr\Container\ContainerInterface;
use QS\Core\Bootstrap\HookLoader;
use QS\Core\Bootstrap\ModuleRegistry;
use QS\Core\Config\ConfigLoader;
use QS\Core\Config\EnvironmentDetector;
use QS\Core\Config\PluginConfig;
use QS\Core\Errors\ErrorHandler;
use QS\Core\Events\EventDispatcher;
use QS\Core\Logging\Logger;
use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\NonceManager;
use QS\Core\Security\RequestSanitizer;
use QS\Core\Versioning\MigrationRunner;
use QS\Core\Versioning\PluginVersion;
use QS\Core\Wordpress\PostTypeRegistrar;
use QS\Core\Wordpress\RestRouteRegistrar;
use QS\Interfaces\Rest\SystemController;
use QS\Modules\Bitacora\Application\CommandHandler\AddBitacoraNoteHandler;
use QS\Modules\Bitacora\Application\CommandHandler\CreateBitacoraHandler;
use QS\Modules\Bitacora\Application\CommandHandler\UpdateBitacoraHandler;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacoraByIdHandler;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacorasHandler;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacoraSummaryHandler;
use QS\Modules\Bitacora\Domain\Policy\BitacoraPolicy;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;
use QS\Modules\Bitacora\Infrastructure\Persistence\CptBitacoraRepository;
use QS\Modules\Bitacora\Infrastructure\Persistence\MetaFieldMapper;
use QS\Modules\Bitacora\Interfaces\Rest\BitacoraController;
use QS\Modules\Booking\Application\QueryHandler\GetAllReservationsHandler;
use QS\Modules\Booking\Application\QueryHandler\GetMuaAgendaHandler;
use QS\Modules\Booking\Application\QueryHandler\GetReservationByIdHandler;
use QS\Modules\Booking\Application\QueryHandler\GetTodayReservationsHandler;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Booking\Infrastructure\Persistence\WpdbLatepointRepository;
use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;
use QS\Modules\Booking\Interfaces\Rest\MuaAgendaController;
use QS\Modules\Booking\Interfaces\Rest\ReservationsController;
use QS\Modules\Finance\Application\CommandHandler\RegisterPaymentHandler;
use QS\Modules\Finance\Application\QueryHandler\GetExpensesHandler;
use QS\Modules\Finance\Application\QueryHandler\GetMonthlyFinanceSummaryHandler;
use QS\Modules\Finance\Application\QueryHandler\GetPaymentsHandler;
use QS\Modules\Finance\Application\QueryHandler\GetServiceMarginHandler;
use QS\Modules\Finance\Domain\Repository\ExpenseRepository;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;
use QS\Modules\Finance\Domain\Service\MarginCalculator;
use QS\Modules\Finance\Domain\Service\MonthlySummaryBuilder;
use QS\Modules\Finance\Infrastructure\Persistence\ExpenseCptRepository;
use QS\Modules\Finance\Infrastructure\Persistence\PaymentCptRepository;
use QS\Modules\Finance\Infrastructure\Persistence\WpServiceCostRepository;
use QS\Modules\Finance\Interfaces\Rest\FinanceController;
use QS\Modules\Agents\Application\CommandHandler\ReindexContentHandler;
use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Agents\Infrastructure\N8n\IngestGateway;
use QS\Modules\Agents\Infrastructure\Wordpress\ReindexAdminPage;
use QS\Modules\Agents\Interfaces\Rest\ChatbotController;
use QS\Modules\IdentityAccess\Application\CommandHandler\AssignQsRoleHandler;
use QS\Modules\IdentityAccess\Application\QueryHandler\GetUserPermissionsHandler;
use QS\Modules\IdentityAccess\Domain\Policy\AccessPolicy;
use QS\Modules\IdentityAccess\Domain\Repository\UserRepository;
use QS\Modules\IdentityAccess\Infrastructure\Persistence\WpUserRepository;
use QS\Modules\IdentityAccess\Infrastructure\Wordpress\RoleRegistrar;
use QS\Modules\IdentityAccess\Interfaces\Hooks\RoleHooks;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetAllServicesHandler;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetServiceByIdHandler;
use QS\Modules\ServicesCatalog\Domain\Repository\ServiceRepository;
use QS\Modules\ServicesCatalog\Infrastructure\Persistence\WpdbServiceCatalogRepository;
use QS\Modules\ServicesCatalog\Interfaces\Rest\ServicesController;
use QS\Modules\Team\Application\QueryHandler\GetAllStaffHandler;
use QS\Modules\Team\Application\QueryHandler\GetStaffAvailabilityHandler;
use QS\Modules\Team\Application\QueryHandler\GetStaffByIdHandler;
use QS\Modules\Team\Domain\Repository\StaffRepository;
use QS\Modules\Team\Domain\Service\AvailabilityChecker;
use QS\Modules\Team\Infrastructure\Persistence\WpdbStaffRepository;
use QS\Modules\Team\Interfaces\Rest\StaffController;
use QS\Shared\Clock\SystemClock;

final class ServiceProvider
{
    /**
     * @return array<string, mixed>
     */
    public static function definitions(string $rootDir): array
    {
        return [
            \wpdb::class => factory(static function (): \wpdb {
                global $wpdb;

                return $wpdb;
            }),
            'qs.root_dir' => $rootDir,
            EnvironmentDetector::class => autowire(),
            ConfigLoader::class => autowire()->constructor($rootDir, get(EnvironmentDetector::class)),
            PluginConfig::class => factory(
                static function (ConfigLoader $configLoader): PluginConfig {
                    $baseConfig = $configLoader->load('plugin.php');
                    $environmentConfig = $configLoader->loadEnvironmentConfig();

                    return new PluginConfig(array_replace_recursive($baseConfig, $environmentConfig));
                }
            ),
            PluginVersion::class => autowire(),
            HookLoader::class => autowire(),
            ModuleRegistry::class => factory(
                static function (ConfigLoader $configLoader): ModuleRegistry {
                    return new ModuleRegistry($configLoader->loadModuleConfigs());
                }
            ),
            ContainerInterface::class => factory(
                static function (DiContainer $container): ContainerInterface {
                    return $container;
                }
            ),
            Logger::class => autowire(),
            ErrorHandler::class => autowire(),
            EventDispatcher::class => autowire(),
            NonceManager::class => autowire(),
            CapabilityChecker::class => autowire(),
            RequestSanitizer::class => autowire(),
            SystemClock::class => autowire(),
            AccessPolicy::class => autowire(),
            UserRepository::class => autowire(WpUserRepository::class),
            RoleRegistrar::class => autowire(),
            RoleHooks::class => autowire(),
            PostTypeRegistrar::class => autowire(),
            RestRouteRegistrar::class => autowire(),
            MetaFieldMapper::class => autowire(),
            BitacoraPolicy::class => autowire(),
            BitacoraRepository::class => autowire(CptBitacoraRepository::class),
            GetBitacorasHandler::class => autowire(),
            GetBitacoraByIdHandler::class => autowire(),
            GetBitacoraSummaryHandler::class => autowire(),
            CreateBitacoraHandler::class => autowire(),
            UpdateBitacoraHandler::class => autowire(),
            AddBitacoraNoteHandler::class => autowire(),
            StaffRepository::class => autowire(WpdbStaffRepository::class),
            AvailabilityChecker::class => autowire(),
            GetAllStaffHandler::class => autowire(),
            GetStaffByIdHandler::class => autowire(),
            GetStaffAvailabilityHandler::class => autowire(),
            LatepointTableMap::class => autowire(),
            ReservationNormalizer::class => autowire(),
            ReservationRepository::class => autowire(WpdbLatepointRepository::class),
            GetAllReservationsHandler::class => autowire(),
            GetTodayReservationsHandler::class => autowire(),
            GetReservationByIdHandler::class => autowire(),
            GetMuaAgendaHandler::class => autowire(),
            ServiceRepository::class => autowire(WpdbServiceCatalogRepository::class),
            GetAllServicesHandler::class => autowire(),
            GetServiceByIdHandler::class => autowire(),
            PaymentRepository::class => autowire(PaymentCptRepository::class),
            ExpenseRepository::class => autowire(ExpenseCptRepository::class),
            ServiceCostRepository::class => autowire(WpServiceCostRepository::class),
            MarginCalculator::class => autowire(),
            MonthlySummaryBuilder::class => autowire(),
            GetMonthlyFinanceSummaryHandler::class => autowire(),
            GetServiceMarginHandler::class => autowire(),
            GetPaymentsHandler::class => autowire(),
            RegisterPaymentHandler::class => autowire(),
            GetExpensesHandler::class => autowire(),
            AssignQsRoleHandler::class => autowire(),
            GetUserPermissionsHandler::class => autowire(),
            MigrationRunner::class => autowire()->constructor(
                $rootDir,
                get(PluginVersion::class),
                get(Logger::class)
            ),
            SystemController::class => autowire(),
            BitacoraController::class => autowire(),
            StaffController::class => autowire(),
            ReservationsController::class => autowire(),
            MuaAgendaController::class => autowire(),
            ServicesController::class => autowire(),
            FinanceController::class => autowire(),
            ChatbotGateway::class => autowire(),
            IngestGateway::class => autowire(),
            ReindexContentHandler::class => autowire(),
            ReindexAdminPage::class => autowire(),
            ChatbotController::class => autowire(),
        ];
    }
}

# Auditoria V1 vs V2 -- qs-manager

Generado por `tools/audit-v1-v2.ps1` -- Fase 1 del plan de migracion.
Analisis textual/regex, no ejecuta PHP. Reproducible con:
```powershell
pwsh qs-manager-v2/tools/audit-v1-v2.ps1
```

## Resumen

| | V1 (`app`) | V2 (`qs-manager-v2/app`) |
|---|---|---|
| Archivos PHP | 206 | 70 |
| Lineas totales | 15119 | 7195 |
| Clases/interfaces/traits declarados | 199 | 69 |
| Referencias WordPress detectadas | 340 | 0 |

OK: V2 no tiene ninguna referencia a WordPress detectada.

## 1. Archivos por tamano (top 20)

### V1

| Lineas | Archivo |
|---|---|
| 1358 | `app/Modules/Agents/Infrastructure/Wordpress/ReindexAdminPage.php` |
| 716 | `app/Modules/Agents/Infrastructure/N8n/ChatbotGateway.php` |
| 449 | `app/Modules/Agents/Infrastructure/Qdrant/QdrantGateway.php` |
| 380 | `app/Modules/Agents/Infrastructure/Chatbot/QuickReplyMatcher.php` |
| 375 | `app/Modules/Agents/Infrastructure/Chatbot/ChatbotProfile.php` |
| 342 | `app/Modules/Agents/Infrastructure/N8n/WhatsAppGateway.php` |
| 306 | `app/Modules/Agents/Interfaces/Rest/ChatbotController.php` |
| 225 | `app/Modules/Booking/Infrastructure/Persistence/WpdbSheetEventRepository.php` |
| 221 | `app/Modules/Booking/Interfaces/Rest/SheetEventsController.php` |
| 218 | `app/Modules/Bitacora/Interfaces/Rest/BitacoraController.php` |
| 215 | `app/Modules/Booking/Interfaces/WP/BookingAdminPage.php` |
| 213 | `app/Modules/Booking/Domain/Entity/SheetEvent.php` |
| 200 | `app/Modules/Agents/Infrastructure/N8n/IngestGateway.php` |
| 194 | `app/Modules/Setup/Interfaces/Cli/QsCommand.php` |
| 192 | `app/Modules/ServicesCatalog/Infrastructure/Persistence/WpdbServiceCatalogRepository.php` |
| 179 | `app/Modules/Agents/Infrastructure/Persistence/WpdbChatLogRepository.php` |
| 178 | `app/Core/Container/ServiceProvider.php` |
| 167 | `app/Modules/Setup/Infrastructure/Wordpress/AgentStatusChecker.php` |
| 163 | `app/Modules/Booking/Infrastructure/Persistence/WpdbLatepointRepository.php` |
| 162 | `app/Modules/Booking/Application/CommandHandler/CreateReservationHandler.php` |

### V2

| Lineas | Archivo |
|---|---|
| 1509 | `qs-manager-v2/app/Infrastructure/Sheets/PostgresSheetReplicaImporter.php` |
| 530 | `qs-manager-v2/app/Infrastructure/Persistence/Postgres/PostgresFinanceReadRepository.php` |
| 459 | `qs-manager-v2/app/Interfaces/Http/WebController.php` |
| 370 | `qs-manager-v2/app/Application/Finance/RebuildFinanceProjection.php` |
| 326 | `qs-manager-v2/app/Interfaces/Http/ServicesController.php` |
| 303 | `qs-manager-v2/app/Domain/Booking/Booking.php` |
| 263 | `qs-manager-v2/app/Infrastructure/Persistence/Postgres/PostgresBookingRepository.php` |
| 226 | `qs-manager-v2/app/Infrastructure/Persistence/Postgres/PostgresServiceRepository.php` |
| 224 | `qs-manager-v2/app/Interfaces/Http/BookingController.php` |
| 190 | `qs-manager-v2/app/Interfaces/Http/Validation/BookingRequestValidator.php` |
| 152 | `qs-manager-v2/app/Interfaces/Http/FinanceController.php` |
| 140 | `qs-manager-v2/app/Domain/ServicesCatalog/Service.php` |
| 131 | `qs-manager-v2/app/Interfaces/Http/SheetSyncController.php` |
| 123 | `qs-manager-v2/app/Infrastructure/Sheets/PostgresSyncRunRepository.php` |
| 122 | `qs-manager-v2/app/Infrastructure/Http/AppFactory.php` |
| 111 | `qs-manager-v2/app/Infrastructure/Sheets/GoogleSheetsCsvReader.php` |
| 111 | `qs-manager-v2/app/Interfaces/Http/Validation/ServiceRequestValidator.php` |
| 85 | `qs-manager-v2/app/Application/Sheets/ProcessSheetSyncRun.php` |
| 84 | `qs-manager-v2/app/Infrastructure/Persistence/Postgres/PostgresStaffRepository.php` |
| 81 | `qs-manager-v2/app/Interfaces/Http/TeamController.php` |

## 2. Funciones/metodos por archivo (top 20)

### V1

| Funciones | Archivo |
|---|---|
| 48 | `app/Modules/Agents/Infrastructure/Wordpress/ReindexAdminPage.php` |
| 34 | `app/Modules/Agents/Infrastructure/N8n/ChatbotGateway.php` |
| 29 | `app/Modules/Booking/Domain/Entity/SheetEvent.php` |
| 19 | `app/Modules/Agents/Infrastructure/Chatbot/ChatbotProfile.php` |
| 18 | `app/Modules/Agents/Infrastructure/N8n/WhatsAppGateway.php` |
| 17 | `app/Modules/Bitacora/Domain/Entity/Bitacora.php` |
| 16 | `app/Modules/Agents/Infrastructure/Chatbot/QuickReplyMatcher.php` |
| 16 | `app/Modules/Booking/Domain/Entity/Reservation.php` |
| 15 | `app/Modules/Bitacora/Interfaces/Rest/BitacoraController.php` |
| 13 | `app/Modules/Team/Domain/Entity/StaffMember.php` |
| 12 | `app/Modules/Agents/Interfaces/Rest/ChatbotController.php` |
| 12 | `app/Modules/Finance/Domain/Entity/MonthlyLedger.php` |
| 12 | `app/Modules/Finance/Interfaces/Rest/FinanceController.php` |
| 11 | `app/Modules/Agents/Infrastructure/Qdrant/QdrantGateway.php` |
| 11 | `app/Modules/Setup/Interfaces/Cli/QsCommand.php` |
| 11 | `app/Modules/Booking/Infrastructure/Persistence/WpdbSheetEventRepository.php` |
| 11 | `app/Modules/ServicesCatalog/Domain/Entity/Service.php` |
| 10 | `app/Modules/Booking/Infrastructure/Persistence/WpdbLatepointRepository.php` |
| 10 | `app/Core/Versioning/PluginVersion.php` |
| 10 | `app/Modules/Team/Interfaces/Rest/StaffController.php` |

### V2

| Funciones | Archivo |
|---|---|
| 43 | `qs-manager-v2/app/Infrastructure/Sheets/PostgresSheetReplicaImporter.php` |
| 26 | `qs-manager-v2/app/Domain/Booking/Booking.php` |
| 17 | `qs-manager-v2/app/Interfaces/Http/ServicesController.php` |
| 11 | `qs-manager-v2/app/Domain/ServicesCatalog/Service.php` |
| 10 | `qs-manager-v2/app/Domain/Finance/Money.php` |
| 10 | `qs-manager-v2/app/Interfaces/Http/BookingController.php` |
| 9 | `qs-manager-v2/app/Application/Finance/RebuildFinanceProjection.php` |
| 9 | `qs-manager-v2/app/Infrastructure/Persistence/Postgres/PostgresServiceRepository.php` |
| 8 | `qs-manager-v2/app/Application/Booking/GasSyncResult.php` |
| 8 | `qs-manager-v2/app/Domain/Team/StaffMember.php` |
| 8 | `qs-manager-v2/app/Infrastructure/Persistence/Postgres/PostgresBookingRepository.php` |
| 7 | `qs-manager-v2/app/Infrastructure/Persistence/Postgres/PostgresFinanceReadRepository.php` |
| 7 | `qs-manager-v2/app/Domain/ServicesCatalog/ServiceRepository.php` |
| 6 | `qs-manager-v2/app/Domain/Booking/BookingRepository.php` |
| 6 | `qs-manager-v2/app/Domain/Finance/FinanceReadRepository.php` |
| 6 | `qs-manager-v2/app/Interfaces/Http/FinanceController.php` |
| 6 | `qs-manager-v2/app/Interfaces/Http/SheetSyncController.php` |
| 6 | `qs-manager-v2/app/Interfaces/Http/TeamController.php` |
| 6 | `qs-manager-v2/app/Interfaces/Http/Validation/BookingRequestValidator.php` |
| 5 | `qs-manager-v2/app/Infrastructure/Sheets/PostgresSyncRunRepository.php` |

## 3. Referencias a WordPress

| Patron | V1 | V2 |
|---|---|---|
| `wp_\w+` | 305 | 0 |
| `WP_\w+` | 305 | 0 |
| `\$wpdb` | 16 | 0 |
| `add_action\s*\(` | 18 | 0 |
| `add_filter\s*\(` | 0 | 0 |
| `register_rest_route\s*\(` | 1 | 0 |
| `do_action\s*\(` | 0 | 0 |
| `apply_filters\s*\(` | 0 | 0 |

## 4. Clases V1 sin referencia detectada (candidatas a sin uso)

Aproximado por grep de texto (nombre corto) dentro de todo el arbol V1, excluyendo el propio archivo de declaracion. Puede haber falsos positivos (uso via reflexion, autoload dinamico, string, etc.) -- revisar manualmente antes de borrar.

| Clase | Archivo |
|---|---|
| `AggregateRoot` | `app/Shared/Domain/AggregateRoot.php` |
| `DateRange` | `app/Shared/ValueObjects/DateRange.php` |
| `EventInterface` | `app/Core/Events/EventInterface.php` |
| `FinanceDashboardPage` | `app/Modules/Finance/Interfaces/Admin/FinanceDashboardPage.php` |
| `GenerateMonthlySummary` | `app/Modules/Finance/Interfaces/Cli/GenerateMonthlySummary.php` |
| `ModuleInterface` | `app/Core/Contracts/ModuleInterface.php` |
| `MonthlyCsvExporter` | `app/Modules/Finance/Infrastructure/Export/MonthlyCsvExporter.php` |
| `PaginatedResult` | `app/Shared/DTO/PaginatedResult.php` |
| `PluginBootstrapper` | `app/Core/Bootstrap/PluginBootstrapper.php` |
| `RepositoryInterface` | `app/Core/Contracts/RepositoryInterface.php` |
| `ReservationAssignment` | `app/Modules/Booking/Domain/Entity/ReservationAssignment.php` |
| `RoleAssignment` | `app/Modules/Team/Domain/Entity/RoleAssignment.php` |
| `ServiceInterface` | `app/Core/Contracts/ServiceInterface.php` |
| `WpTestCase` | `app/Shared/Testing/WpTestCase.php` |

Total: 14 de 199 clases V1.

## 5. Clases V1 no presentes en V2 (por nombre corto)

Comparacion por nombre corto de clase (no namespace completo) -- una coincidencia de nombre no garantiza que sea la misma responsabilidad, solo indica que no hay ninguna clase con ese nombre en V2 todavia.

| Clase V1 | Archivo V1 |
|---|---|
| `AccessPolicy` | `app/Modules/IdentityAccess/Domain/Policy/AccessPolicy.php` |
| `ActivationHookInterface` | `app/Core/Contracts/ActivationHookInterface.php` |
| `ActivationSetupHooks` | `app/Modules/Setup/Interfaces/Hooks/ActivationSetupHooks.php` |
| `AddBitacoraNote` | `app/Modules/Bitacora/Application/Command/AddBitacoraNote.php` |
| `AddBitacoraNoteHandler` | `app/Modules/Bitacora/Application/CommandHandler/AddBitacoraNoteHandler.php` |
| `AgentsServiceProvider` | `app/Modules/Agents/AgentsServiceProvider.php` |
| `AgentStatusChecker` | `app/Modules/Setup/Infrastructure/Wordpress/AgentStatusChecker.php` |
| `AggregateRoot` | `app/Shared/Domain/AggregateRoot.php` |
| `AssignQsRole` | `app/Modules/IdentityAccess/Application/Command/AssignQsRole.php` |
| `AssignQsRoleHandler` | `app/Modules/IdentityAccess/Application/CommandHandler/AssignQsRoleHandler.php` |
| `AvailabilityChecker` | `app/Modules/Team/Domain/Service/AvailabilityChecker.php` |
| `AvailabilityWindow` | `app/Modules/Team/Domain/ValueObject/AvailabilityWindow.php` |
| `Bitacora` | `app/Modules/Bitacora/Domain/Entity/Bitacora.php` |
| `BitacoraAdminPage` | `app/Modules/Bitacora/Interfaces/Admin/BitacoraAdminPage.php` |
| `BitacoraController` | `app/Modules/Bitacora/Interfaces/Rest/BitacoraController.php` |
| `BitacoraDTO` | `app/Modules/Bitacora/Application/DTO/BitacoraDTO.php` |
| `BitacoraPolicy` | `app/Modules/Bitacora/Domain/Policy/BitacoraPolicy.php` |
| `BitacoraRepository` | `app/Modules/Bitacora/Domain/Repository/BitacoraRepository.php` |
| `BitacoraServiceProvider` | `app/Modules/Bitacora/BitacoraServiceProvider.php` |
| `BookingAdminPage` | `app/Modules/Booking/Interfaces/WP/BookingAdminPage.php` |
| `BookingConflictException` | `app/Modules/Booking/Domain/Exception/BookingConflictException.php` |
| `BookingServiceProvider` | `app/Modules/Booking/BookingServiceProvider.php` |
| `CalendarGateway` | `app/Modules/Booking/Domain/Service/CalendarGateway.php` |
| `CapabilityChecker` | `app/Core/Security/CapabilityChecker.php` |
| `ChatbotController` | `app/Modules/Agents/Interfaces/Rest/ChatbotController.php` |
| `ChatbotFallbackResponder` | `app/Modules/Agents/Infrastructure/Wordpress/ChatbotFallbackResponder.php` |
| `ChatbotGateway` | `app/Modules/Agents/Infrastructure/N8n/ChatbotGateway.php` |
| `ChatbotProfile` | `app/Modules/Agents/Infrastructure/Chatbot/ChatbotProfile.php` |
| `ChatbotShortcode` | `app/Modules/Agents/Infrastructure/Wordpress/ChatbotShortcode.php` |
| `CliCommandRegistrar` | `app/Modules/Setup/Interfaces/Cli/CliCommandRegistrar.php` |
| `CommandBus` | `app/Shared/Bus/CommandBus.php` |
| `CommandHandlerInterface` | `app/Shared/Bus/CommandHandlerInterface.php` |
| `CommandInterface` | `app/Shared/Bus/CommandInterface.php` |
| `ConfigLoader` | `app/Core/Config/ConfigLoader.php` |
| `ContainerBuilder` | `app/Core/Container/ContainerBuilder.php` |
| `CptBitacoraRepository` | `app/Modules/Bitacora/Infrastructure/Persistence/CptBitacoraRepository.php` |
| `CreateBitacora` | `app/Modules/Bitacora/Application/Command/CreateBitacora.php` |
| `CreateBitacoraHandler` | `app/Modules/Bitacora/Application/CommandHandler/CreateBitacoraHandler.php` |
| `CreateReservation` | `app/Modules/Booking/Application/Command/CreateReservation.php` |
| `CreateReservationHandler` | `app/Modules/Booking/Application/CommandHandler/CreateReservationHandler.php` |
| `DateRange` | `app/Shared/ValueObjects/DateRange.php` |
| `Entity` | `app/Shared/Domain/Entity.php` |
| `EnvironmentDetector` | `app/Core/Config/EnvironmentDetector.php` |
| `ErrorHandler` | `app/Core/Errors/ErrorHandler.php` |
| `EventDispatcher` | `app/Core/Events/EventDispatcher.php` |
| `EventInterface` | `app/Core/Events/EventInterface.php` |
| `Expense` | `app/Modules/Finance/Domain/Entity/Expense.php` |
| `ExpenseCptRepository` | `app/Modules/Finance/Infrastructure/Persistence/ExpenseCptRepository.php` |
| `ExpenseDTO` | `app/Modules/Finance/Application/DTO/ExpenseDTO.php` |
| `ExpenseRepository` | `app/Modules/Finance/Domain/Repository/ExpenseRepository.php` |
| `FinanceDashboardPage` | `app/Modules/Finance/Interfaces/Admin/FinanceDashboardPage.php` |
| `FinanceServiceProvider` | `app/Modules/Finance/FinanceServiceProvider.php` |
| `GenerateMonthlySummary` | `app/Modules/Finance/Interfaces/Cli/GenerateMonthlySummary.php` |
| `GetAllReservations` | `app/Modules/Booking/Application/Query/GetAllReservations.php` |
| `GetAllReservationsHandler` | `app/Modules/Booking/Application/QueryHandler/GetAllReservationsHandler.php` |
| `GetAllServices` | `app/Modules/ServicesCatalog/Application/Query/GetAllServices.php` |
| `GetAllServicesHandler` | `app/Modules/ServicesCatalog/Application/QueryHandler/GetAllServicesHandler.php` |
| `GetAllStaff` | `app/Modules/Team/Application/Query/GetAllStaff.php` |
| `GetAllStaffHandler` | `app/Modules/Team/Application/QueryHandler/GetAllStaffHandler.php` |
| `GetBitacoraById` | `app/Modules/Bitacora/Application/Query/GetBitacoraById.php` |
| `GetBitacoraByIdHandler` | `app/Modules/Bitacora/Application/QueryHandler/GetBitacoraByIdHandler.php` |
| `GetBitacoras` | `app/Modules/Bitacora/Application/Query/GetBitacoras.php` |
| `GetBitacorasHandler` | `app/Modules/Bitacora/Application/QueryHandler/GetBitacorasHandler.php` |
| `GetBitacoraSummary` | `app/Modules/Bitacora/Application/Query/GetBitacoraSummary.php` |
| `GetBitacoraSummaryHandler` | `app/Modules/Bitacora/Application/QueryHandler/GetBitacoraSummaryHandler.php` |
| `GetExpenses` | `app/Modules/Finance/Application/Query/GetExpenses.php` |
| `GetExpensesHandler` | `app/Modules/Finance/Application/QueryHandler/GetExpensesHandler.php` |
| `GetMonthlyFinanceSummary` | `app/Modules/Finance/Application/Query/GetMonthlyFinanceSummary.php` |
| `GetMonthlyFinanceSummaryHandler` | `app/Modules/Finance/Application/QueryHandler/GetMonthlyFinanceSummaryHandler.php` |
| `GetMuaAgenda` | `app/Modules/Booking/Application/Query/GetMuaAgenda.php` |
| `GetMuaAgendaHandler` | `app/Modules/Booking/Application/QueryHandler/GetMuaAgendaHandler.php` |
| `GetPayments` | `app/Modules/Finance/Application/Query/GetPayments.php` |
| `GetPaymentsHandler` | `app/Modules/Finance/Application/QueryHandler/GetPaymentsHandler.php` |
| `GetReservationById` | `app/Modules/Booking/Application/Query/GetReservationById.php` |
| `GetReservationByIdHandler` | `app/Modules/Booking/Application/QueryHandler/GetReservationByIdHandler.php` |
| `GetServiceById` | `app/Modules/ServicesCatalog/Application/Query/GetServiceById.php` |
| `GetServiceByIdHandler` | `app/Modules/ServicesCatalog/Application/QueryHandler/GetServiceByIdHandler.php` |
| `GetServiceMargin` | `app/Modules/Finance/Application/Query/GetServiceMargin.php` |
| `GetServiceMarginHandler` | `app/Modules/Finance/Application/QueryHandler/GetServiceMarginHandler.php` |
| `GetStaffAvailability` | `app/Modules/Team/Application/Query/GetStaffAvailability.php` |
| `GetStaffAvailabilityHandler` | `app/Modules/Team/Application/QueryHandler/GetStaffAvailabilityHandler.php` |
| `GetStaffById` | `app/Modules/Team/Application/Query/GetStaffById.php` |
| `GetStaffByIdHandler` | `app/Modules/Team/Application/QueryHandler/GetStaffByIdHandler.php` |
| `GetTodayReservations` | `app/Modules/Booking/Application/Query/GetTodayReservations.php` |
| `GetTodayReservationsHandler` | `app/Modules/Booking/Application/QueryHandler/GetTodayReservationsHandler.php` |
| `GetUserPermissions` | `app/Modules/IdentityAccess/Application/Query/GetUserPermissions.php` |
| `GetUserPermissionsHandler` | `app/Modules/IdentityAccess/Application/QueryHandler/GetUserPermissionsHandler.php` |
| `HookableInterface` | `app/Core/Contracts/HookableInterface.php` |
| `HookLoader` | `app/Core/Bootstrap/HookLoader.php` |
| `IdentityAccessServiceProvider` | `app/Modules/IdentityAccess/IdentityAccessServiceProvider.php` |
| `IngestGateway` | `app/Modules/Agents/Infrastructure/N8n/IngestGateway.php` |
| `LatepointTableMap` | `app/Modules/Booking/Infrastructure/Wordpress/LatepointTableMap.php` |
| `Logger` | `app/Core/Logging/Logger.php` |
| `MarginCalculator` | `app/Modules/Finance/Domain/Service/MarginCalculator.php` |
| `MenuProvisioner` | `app/Modules/Setup/Infrastructure/Wordpress/MenuProvisioner.php` |
| `MetaFieldMapper` | `app/Modules/Bitacora/Infrastructure/Persistence/MetaFieldMapper.php` |
| `MigrationRunner` | `app/Core/Versioning/MigrationRunner.php` |
| `ModuleInterface` | `app/Core/Contracts/ModuleInterface.php` |
| `ModuleRegistry` | `app/Core/Bootstrap/ModuleRegistry.php` |
| `ModuleServiceProviderInterface` | `app/Core/Contracts/ModuleServiceProviderInterface.php` |
| `MonthlyCsvExporter` | `app/Modules/Finance/Infrastructure/Export/MonthlyCsvExporter.php` |
| `MonthlyLedger` | `app/Modules/Finance/Domain/Entity/MonthlyLedger.php` |
| `MonthlySummaryBuilder` | `app/Modules/Finance/Domain/Service/MonthlySummaryBuilder.php` |
| `MonthlySummaryDTO` | `app/Modules/Finance/Application/DTO/MonthlySummaryDTO.php` |
| `MuaAgendaController` | `app/Modules/Booking/Interfaces/Rest/MuaAgendaController.php` |
| `N8nCalendarGateway` | `app/Modules/Booking/Infrastructure/N8n/N8nCalendarGateway.php` |
| `N8nSheetsSyncGateway` | `app/Modules/Booking/Infrastructure/N8n/N8nSheetsSyncGateway.php` |
| `NonceManager` | `app/Core/Security/NonceManager.php` |
| `OptionProvisioner` | `app/Modules/Setup/Infrastructure/Wordpress/OptionProvisioner.php` |
| `PageProvisioner` | `app/Modules/Setup/Infrastructure/Wordpress/PageProvisioner.php` |
| `PaginatedResult` | `app/Shared/DTO/PaginatedResult.php` |
| `Payment` | `app/Modules/Finance/Domain/Entity/Payment.php` |
| `PaymentCptRepository` | `app/Modules/Finance/Infrastructure/Persistence/PaymentCptRepository.php` |
| `PaymentDTO` | `app/Modules/Finance/Application/DTO/PaymentDTO.php` |
| `PaymentRepository` | `app/Modules/Finance/Domain/Repository/PaymentRepository.php` |
| `PermalinkProvisioner` | `app/Modules/Setup/Infrastructure/Wordpress/PermalinkProvisioner.php` |
| `PickupPoint` | `app/Modules/Bitacora/Domain/ValueObject/PickupPoint.php` |
| `PluginBootstrapper` | `app/Core/Bootstrap/PluginBootstrapper.php` |
| `PluginConfig` | `app/Core/Config/PluginConfig.php` |
| `PluginVersion` | `app/Core/Versioning/PluginVersion.php` |
| `PostTypeRegistrar` | `app/Core/Wordpress/PostTypeRegistrar.php` |
| `QdrantGateway` | `app/Modules/Agents/Infrastructure/Qdrant/QdrantGateway.php` |
| `QsCommand` | `app/Modules/Setup/Interfaces/Cli/QsCommand.php` |
| `QsException` | `app/Core/Errors/QsException.php` |
| `QsUser` | `app/Modules/IdentityAccess/Domain/Entity/QsUser.php` |
| `QueryBus` | `app/Shared/Bus/QueryBus.php` |
| `QueryHandlerInterface` | `app/Shared/Bus/QueryHandlerInterface.php` |
| `QuickReplyMatcher` | `app/Modules/Agents/Infrastructure/Chatbot/QuickReplyMatcher.php` |
| `RegisterPayment` | `app/Modules/Finance/Application/Command/RegisterPayment.php` |
| `RegisterPaymentHandler` | `app/Modules/Finance/Application/CommandHandler/RegisterPaymentHandler.php` |
| `ReindexAdminPage` | `app/Modules/Agents/Infrastructure/Wordpress/ReindexAdminPage.php` |
| `ReindexContentHandler` | `app/Modules/Agents/Application/CommandHandler/ReindexContentHandler.php` |
| `RepositoryInterface` | `app/Core/Contracts/RepositoryInterface.php` |
| `RequestSanitizer` | `app/Core/Security/RequestSanitizer.php` |
| `Reservation` | `app/Modules/Booking/Domain/Entity/Reservation.php` |
| `ReservationAssignment` | `app/Modules/Booking/Domain/Entity/ReservationAssignment.php` |
| `ReservationDTO` | `app/Modules/Booking/Application/DTO/ReservationDTO.php` |
| `ReservationId` | `app/Modules/Booking/Domain/ValueObject/ReservationId.php` |
| `ReservationNormalizer` | `app/Modules/Booking/Domain/Service/ReservationNormalizer.php` |
| `ReservationRepository` | `app/Modules/Booking/Domain/Repository/ReservationRepository.php` |
| `ReservationsController` | `app/Modules/Booking/Interfaces/Rest/ReservationsController.php` |
| `ReservationTimeRange` | `app/Modules/Booking/Domain/ValueObject/ReservationTimeRange.php` |
| `RestResponse` | `app/Shared/DTO/RestResponse.php` |
| `RestRouteRegistrar` | `app/Core/Wordpress/RestRouteRegistrar.php` |
| `RoleAssignment` | `app/Modules/Team/Domain/Entity/RoleAssignment.php` |
| `RoleHooks` | `app/Modules/IdentityAccess/Interfaces/Hooks/RoleHooks.php` |
| `RoleRegistrar` | `app/Modules/IdentityAccess/Infrastructure/Wordpress/RoleRegistrar.php` |
| `RoutePlan` | `app/Modules/Bitacora/Domain/Entity/RoutePlan.php` |
| `ServiceAddress` | `app/Modules/Bitacora/Domain/ValueObject/ServiceAddress.php` |
| `ServiceCostRepository` | `app/Modules/Finance/Domain/Repository/ServiceCostRepository.php` |
| `ServiceDTO` | `app/Modules/ServicesCatalog/Application/DTO/ServiceDTO.php` |
| `ServiceInterface` | `app/Core/Contracts/ServiceInterface.php` |
| `ServiceMarginDTO` | `app/Modules/Finance/Application/DTO/ServiceMarginDTO.php` |
| `ServicePrice` | `app/Modules/ServicesCatalog/Domain/ValueObject/ServicePrice.php` |
| `ServiceProvider` | `app/Core/Container/ServiceProvider.php` |
| `ServicesCatalogServiceProvider` | `app/Modules/ServicesCatalog/ServicesCatalogServiceProvider.php` |
| `SetupController` | `app/Modules/Setup/Interfaces/Rest/SetupController.php` |
| `SetupServiceProvider` | `app/Modules/Setup/SetupServiceProvider.php` |
| `SetupSiteCommand` | `app/Modules/Setup/Application/Command/SetupSiteCommand.php` |
| `SetupSiteHandler` | `app/Modules/Setup/Application/CommandHandler/SetupSiteHandler.php` |
| `SheetEvent` | `app/Modules/Booking/Domain/Entity/SheetEvent.php` |
| `SheetEventRepository` | `app/Modules/Booking/Domain/Repository/SheetEventRepository.php` |
| `SheetEventsController` | `app/Modules/Booking/Interfaces/Rest/SheetEventsController.php` |
| `SheetRowData` | `app/Modules/Booking/Domain/Service/SheetRowData.php` |
| `SheetsSyncGateway` | `app/Modules/Booking/Domain/Service/SheetsSyncGateway.php` |
| `StaffController` | `app/Modules/Team/Interfaces/Rest/StaffController.php` |
| `StaffDTO` | `app/Modules/Team/Application/DTO/StaffDTO.php` |
| `SystemClock` | `app/Shared/Clock/SystemClock.php` |
| `SystemController` | `app/Interfaces/Rest/SystemController.php` |
| `TeamServiceProvider` | `app/Modules/Team/TeamServiceProvider.php` |
| `TestCase` | `app/Shared/Testing/TestCase.php` |
| `TravelDuration` | `app/Modules/Bitacora/Domain/ValueObject/TravelDuration.php` |
| `TravelNote` | `app/Modules/Bitacora/Domain/Entity/TravelNote.php` |
| `UpdateBitacora` | `app/Modules/Bitacora/Application/Command/UpdateBitacora.php` |
| `UpdateBitacoraHandler` | `app/Modules/Bitacora/Application/CommandHandler/UpdateBitacoraHandler.php` |
| `UserId` | `app/Shared/ValueObjects/UserId.php` |
| `UserPermissionsDTO` | `app/Modules/IdentityAccess/Application/DTO/UserPermissionsDTO.php` |
| `UserRepository` | `app/Modules/IdentityAccess/Domain/Repository/UserRepository.php` |
| `ValueObject` | `app/Shared/Domain/ValueObject.php` |
| `WhatsAppGateway` | `app/Modules/Agents/Infrastructure/N8n/WhatsAppGateway.php` |
| `WhatsAppOptionsController` | `app/Modules/Agents/Interfaces/Rest/WhatsAppOptionsController.php` |
| `WpdbChatLogRepository` | `app/Modules/Agents/Infrastructure/Persistence/WpdbChatLogRepository.php` |
| `WpdbLatepointRepository` | `app/Modules/Booking/Infrastructure/Persistence/WpdbLatepointRepository.php` |
| `WpdbServiceCatalogRepository` | `app/Modules/ServicesCatalog/Infrastructure/Persistence/WpdbServiceCatalogRepository.php` |
| `WpdbSheetEventRepository` | `app/Modules/Booking/Infrastructure/Persistence/WpdbSheetEventRepository.php` |
| `WpdbStaffRepository` | `app/Modules/Team/Infrastructure/Persistence/WpdbStaffRepository.php` |
| `WpServiceCostRepository` | `app/Modules/Finance/Infrastructure/Persistence/WpServiceCostRepository.php` |
| `WpTestCase` | `app/Shared/Testing/WpTestCase.php` |
| `WpUserRepository` | `app/Modules/IdentityAccess/Infrastructure/Persistence/WpUserRepository.php` |

Total: 189 de 199 clases V1 no tienen homonimo en V2.



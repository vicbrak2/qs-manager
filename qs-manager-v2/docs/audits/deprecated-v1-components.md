# Componentes V1 marcados "no migrar" — Fase 2

Basado en `migration-audit.md` (Fase 1) + revisión manual dirigida por patrón de
ruta/contenido. Cada componente tiene una razón explícita, según el criterio de
cierre de Fase 2: **WordPress, N8n, Qdrant, LatePoint, admin UI o duplicado**.

Los patrones literales del plan (`Agents/Infrastructure/Wordpress/*`, `*/N8n/*`,
`*/Qdrant/*`, `Interfaces/Rest/*Controller.php`, `Wpdb*`, `*CptRepository`,
`Core/Wordpress/*`) no cubrían todo lo acoplado a esas mismas dependencias — se
amplió con grep de contenido (`wp_*`, `WP_*`, `$wpdb`, `add_action`, `add_filter`,
`register_rest_route`, `n8n`, `qdrant`, `latepoint`, case-insensitive) sobre los
206 archivos de V1. Resultado: **49 archivos** con acoplamiento detectado, más
2 `ServiceProvider` de wiring que quedan aparte por tener responsabilidad mixta.

## Razón: WordPress (hooks, `$wpdb`, admin pages, `register_rest_route`)

| Archivo | Detalle |
|---|---|
| `app/Core/Bootstrap/PluginBootstrapper.php` | Arranque del plugin WP |
| `app/Core/Config/EnvironmentDetector.php` | Detecta entorno vía constantes/funciones WP |
| `app/Core/Container/ServiceProvider.php` | DI container atado al ciclo de vida del plugin |
| `app/Core/Security/NonceManager.php` | Nonces de WP (`wp_verify_nonce`, etc.) |
| `app/Core/Versioning/MigrationRunner.php` | Migraciones de schema vía `$wpdb` |
| `app/Core/Wordpress/PostTypeRegistrar.php` | Registra Custom Post Types |
| `app/Core/Wordpress/RestRouteRegistrar.php` | `register_rest_route` |
| `app/Interfaces/Rest/SystemController.php` | Endpoint REST vía WP REST API |
| `app/Modules/Agents/Infrastructure/Wordpress/ChatbotFallbackResponder.php` | Fallback de chatbot atado a hooks WP |
| `app/Modules/Agents/Infrastructure/Wordpress/ChatbotShortcode.php` | Shortcode de WP |
| `app/Modules/Agents/Infrastructure/Wordpress/ReindexAdminPage.php` | Admin page (1358 líneas — el archivo más grande de V1) |
| `app/Modules/Agents/Interfaces/Rest/ChatbotController.php` | REST vía WP (también N8n, ver abajo) |
| `app/Modules/Agents/Interfaces/Rest/WhatsAppOptionsController.php` | REST vía WP |
| `app/Modules/Bitacora/Infrastructure/Persistence/CptBitacoraRepository.php` | Repositorio sobre Custom Post Type |
| `app/Modules/Bitacora/Infrastructure/Persistence/MetaFieldMapper.php` | Mapea post meta de WP |
| `app/Modules/Bitacora/Interfaces/Admin/BitacoraAdminPage.php` | Admin page |
| `app/Modules/Bitacora/Interfaces/Rest/BitacoraController.php` | REST vía WP — **pendiente**: V2 aún no tiene controller de Bitácora, mantener como referencia hasta migrar el dominio (Fase 3) |
| `app/Modules/Booking/Infrastructure/Persistence/WpdbLatepointRepository.php` | `$wpdb` + LatePoint (ver también razón LatePoint) |
| `app/Modules/Booking/Infrastructure/Persistence/WpdbSheetEventRepository.php` | `$wpdb` |
| `app/Modules/Booking/Interfaces/Rest/MuaAgendaController.php` | REST vía WP — **duplicado**: V2 ya tiene `Interfaces/Http/BookingController.php` |
| `app/Modules/Booking/Interfaces/Rest/ReservationsController.php` | REST vía WP — **duplicado**: ídem |
| `app/Modules/Booking/Interfaces/Rest/SheetEventsController.php` | REST vía WP (también N8n) |
| `app/Modules/Booking/Interfaces/WP/BookingAdminPage.php` | Admin page |
| `app/Modules/Finance/Infrastructure/Persistence/ExpenseCptRepository.php` | Repositorio sobre CPT |
| `app/Modules/Finance/Infrastructure/Persistence/PaymentCptRepository.php` | Repositorio sobre CPT |
| `app/Modules/Finance/Infrastructure/Persistence/WpServiceCostRepository.php` | `$wpdb` (no capturado por el glob `Wpdb*`, prefijo `Wp` sin `db`) |
| `app/Modules/Finance/Interfaces/Admin/FinanceDashboardPage.php` | Admin page |
| `app/Modules/Finance/Interfaces/Rest/FinanceController.php` | REST vía WP — **duplicado**: V2 ya tiene `Interfaces/Http/FinanceController.php` |
| `app/Modules/IdentityAccess/Infrastructure/Persistence/WpUserRepository.php` | Usuarios de WP |
| `app/Modules/IdentityAccess/Infrastructure/Wordpress/RoleRegistrar.php` | Roles/capabilities de WP |
| `app/Modules/IdentityAccess/Interfaces/Hooks/RoleHooks.php` | Hooks de WP |
| `app/Modules/ServicesCatalog/Infrastructure/Persistence/WpdbServiceCatalogRepository.php` | `$wpdb` |
| `app/Modules/ServicesCatalog/Interfaces/Rest/ServicesController.php` | REST vía WP — **duplicado**: V2 ya tiene `Interfaces/Http/ServicesController.php` |
| `app/Modules/Setup/Infrastructure/Wordpress/AgentStatusChecker.php` | Health-check atado a WP (también N8n/Qdrant) |
| `app/Modules/Setup/Infrastructure/Wordpress/MenuProvisioner.php` | Menú de admin de WP |
| `app/Modules/Setup/Infrastructure/Wordpress/PageProvisioner.php` | Páginas de WP |
| `app/Modules/Setup/Interfaces/Cli/CliCommandRegistrar.php` | Registra comandos WP-CLI |
| `app/Modules/Setup/Interfaces/Cli/QsCommand.php` | Comando WP-CLI (también N8n/Qdrant) |
| `app/Modules/Setup/Interfaces/Rest/SetupController.php` | REST vía WP |
| `app/Modules/Team/Infrastructure/Persistence/WpdbStaffRepository.php` | `$wpdb` |
| `app/Modules/Team/Interfaces/Rest/StaffController.php` | REST vía WP — **duplicado**: V2 ya tiene `Interfaces/Http/TeamController.php` |

## Razón: N8n

| Archivo | Detalle |
|---|---|
| `app/Modules/Agents/Infrastructure/N8n/ChatbotGateway.php` | 716 líneas, gateway HTTP a workflows de n8n |
| `app/Modules/Agents/Infrastructure/N8n/IngestGateway.php` | Ingesta hacia n8n (también Qdrant) |
| `app/Modules/Agents/Infrastructure/N8n/WhatsAppGateway.php` | WhatsApp vía n8n |
| `app/Modules/Booking/Infrastructure/N8n/N8nCalendarGateway.php` | Calendario vía n8n |
| `app/Modules/Booking/Infrastructure/N8n/N8nSheetsSyncGateway.php` | Sync de Sheets vía n8n — **superado**: V2 ya resuelve esto directo con `Infrastructure/Sheets/*` (Google Sheets API / Postgres), sin pasar por n8n |

## Razón: Qdrant

| Archivo | Detalle |
|---|---|
| `app/Modules/Agents/Infrastructure/Qdrant/QdrantGateway.php` | 449 líneas, cliente de la base vectorial Qdrant (RAG del chatbot) |

## Razón: LatePoint

| Archivo | Detalle |
|---|---|
| `app/Modules/Booking/Infrastructure/Wordpress/LatepointTableMap.php` | Mapea tablas del plugin LatePoint |
| `app/Modules/Booking/Infrastructure/Persistence/WpdbLatepointRepository.php` | Lee reservas desde tablas de LatePoint (doble razón: WordPress + LatePoint) |

## Requiere revisión manual (no se deprecan directo — wiring DI mixto)

Estos `ServiceProvider` de módulo declaran únicamente `autowire()` hacia las
clases de infraestructura de arriba (todas WP/N8n/Qdrant/LatePoint). No se
migran tal cual — al reconstruir cada módulo en V2 (Fase 3), su wiring se
rehace desde cero apuntando a las nuevas clases de dominio/infraestructura de
V2, no a un archivo traducido de V1.

| Archivo | Por qué no se deprecó directo |
|---|---|
| `app/Modules/Agents/AgentsServiceProvider.php` | Wiring 100% hacia clases N8n/Qdrant/Wordpress de arriba — sin domain propio que migrar; el módulo Agents no está en la prioridad de Fase 3 |
| `app/Modules/Booking/BookingServiceProvider.php` | Wiring hacia N8n/Wpdb/LatePoint/WP de arriba, pero también hacia `Domain/Repository`, `Application/Command(Handler)` de Booking que SÍ hay que revisar antes de migrar (Fase 3, prioridad 2) |

## Resumen

| Razón | Archivos |
|---|---|
| WordPress | 41 filas (1 solapa con LatePoint: `WpdbLatepointRepository.php`) |
| N8n | 5 filas |
| Qdrant | 1 fila |
| LatePoint | 2 filas (1 solapa con WordPress, ver arriba) |
| Revisión manual (wiring) | 2 |
| **Total archivos únicos marcados** | **50** de los 206 de V1 (~24%) |

De estos, **5 ya son estrictamente redundantes** porque V2 tiene su propio
controller equivalente (`ServicesController`, `FinanceController`,
`StaffController`/TeamController, `ReservationsController`/`MuaAgendaController`
→ `BookingController`) — se puede confirmar su baja sin esperar a que termine
la migración de dominio, ya que la interfaz HTTP no depende de que el dominio
subyacente esté 100% portado.

`BitacoraController.php` es el único controller de la lista `Interfaces/Rest/*`
que **todavía no tiene equivalente en V2** — no depreciar hasta completar la
migración de Bitácora (Fase 3, prioridad 3).

# QS Manager: Stack Tecnológico y Arquitectura (qs-core)

## Lenguaje y Plataforma Base
*   **Backend:** PHP 8.1+ (plugin `qs-core`). Requiere PHP 8.1 por características como return types, enums nativos y readonly properties. Mejora dramática sobre 7.4 sin costo de migración alto.
*   **Plataforma:** WordPress 6.9.x (ya instalada).
*   **Persistencia:** MySQL/MariaDB (ya disponible).

## Plugin `qs-core`
*   **Arquitectura:** Monolito modular (DDD lite).
*   **Namespace:** `QS\Core`.
*   **Autoload:** PSR-4 via Composer.
*   **Contenedor de Inyección de Dependencias (DI):** PHP-DI (free, liviano, compatible con WP).
*   **Configuración:** `phpdotenv` o WP Options según el entorno.
*   **Eventos Internos:** Hooks de WordPress encapsulados.

## Persistencia
*   **Custom Post Types (CPT) de WordPress:** `qs_bitacora`, `qs_service`, `qs_payment`, `qs_expense`.
*   **Tablas Custom:** `wp_qs_staff`, `wp_qs_booking_snapshots`, `wp_qs_leads_timeline`, `wp_qs_audit_log`.
*   **`$wpdb` Adapter:** Lectura LatePoint (encapsulado en `LatepointGateway`).
*   **WP Transients:** Caché de agenda y resúmenes (TTL corto). Sin Redis ni Memcached; WP Transients es suficiente para el volumen de QS.

## API
*   **WordPress REST API nativa:** `/wp-json/qs/v1/*`.
*   **Autenticación:** Application Passwords (nativo de WP, sin plugin adicional).
*   **Formato de respuesta:** JSON.
*   **Versionado:** `v1` en el namespace, iterando a `v2` cuando se rompa el contrato. Sin GraphQL.

## Frontend Admin (Dashboard Interno)
*   **Framework:** React 18 embebido en `wp-admin` usando `wp_enqueue_script`.
*   **Build Tooling:** `@wordpress/scripts` (oficial y gratuito).
*   **Cliente REST:** `@wordpress/api-fetch` con nonce automático.
*   **State Management:** Zustand (liviano, reemplaza a Redux).
*   **Estilos:** TailwindCSS.

## Automatización Externa
*   **Plataforma:** n8n self-hosted (en Railway o Render tier gratuito).
*   **Workflows principales:** Reserva → Mensaje por WhatsApp, bitácora automática, reporte mensual.
*   **Trigger:** Webhooks emitidos por `qs-core` al confirmar una reserva.

## Calidad y Testing
*   **Tests:** PHPUnit 10+ para unit e integration tests.
*   **Mocks:** Brain Monkey (o `WP_Mock`) para simular funciones de WordPress en unit tests.
*   **Mutation Testing:** Infection PHP (prioridad en dominios: Finance, Booking, Bitácora). Empezará a usarse a partir de la Fase 2.
*   **Static Analysis:** PHPStan nivel 6 (escalable a nivel 8 con el tiempo).
*   **Formateo:** PHP CS Fixer.

### Quality Gates Iniciales Mínimos
*   PHPStan sin errores en nivel 6.
*   Unit tests pasan en CI/CD.
*   Cobertura Domain/Application > 80% (solo módulos Fase 1).

### Validacion de estructura
*   `composer run validate:structure` valida que la raiz del repo no acumule archivos fuera de politica.
*   `composer install` y `composer update` instalan un `pre-commit` local que ejecuta esa validacion antes de cada commit.
*   En Windows, si no tienes `composer`/`php` en `PATH`, puedes instalar el hook con `pwsh -NoProfile -File tools/git-hooks/install.ps1`.
*   CI tambien ejecuta la misma regla antes de quality y package.

## CI/CD
*   **Plataforma:** GitHub Actions (gratis para repos privados).
*   **Pipeline:** Lint → Static Analysis → Tests → Build Assets.
*   **Deploy:** `git pull` + WP-CLI en el servidor (si hay SSH) o mediante plugin de deploy vía webhook (no se usará Docker en producción por el hosting compartido de QS).

### Packaging y Deploy
*   **Packaging local:** `php tools/package-plugin.php` o `composer run package`.
*   **Salida:** `dist/qs-core/` y `dist/qs-core.zip`.
*   **Deploy automático:** push a `main` luego de pasar `quality`, reutilizando el artifact generado en `package` y desplegando por FTP incremental.
*   **Secrets requeridos en GitHub:** `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.
*   **Estado remoto de sync FTP:** `.ftp-deploy-sync-state.json` en la raíz remota del usuario FTP.

### Scripts locales para probar la API
Si `QS_API_BASE`, `QS_API_USER` y `QS_API_PASS` existen en `.env`, los scripts los leen automáticamente:

```powershell
powershell -ExecutionPolicy Bypass -File tools/http/Get-QsBookingsToday.ps1
```

O para cualquier endpoint:

```powershell
powershell -ExecutionPolicy Bypass -File tools/http/Invoke-QsApi.ps1 -Path 'health'
```

Si quieres persistir esos valores como variables de entorno de usuario en Windows, el helper sigue disponible:

```powershell
powershell -ExecutionPolicy Bypass -File tools/http/Set-QsApiEnv.ps1
```

## Roles Operativos Iniciales (3 roles)
*   `qs_admin`: Administrador global (ej. Víctor - acceso total).
*   `qs_coordinadora`: Gestión operativa (ej. Camila Villalobos).
*   `qs_staff`: Profesionales (MUAs, estilista) con acceso limitado a su propia agenda y bitácora.

---

## Fases de Implementación

### Fase 0: Core, Bootstrap, Config, Migrations
*   **Duración Est.:** 1 semana
*   **Objetivos:**
    *   Setup del proyecto (Composer, npm, configuraciones básicas).
    *   Estructura base del plugin `qs-core` y patrón DDD lite.
    *   Integración e inicialización del contenedor PHP-DI.
    *   Sistema de migraciones o instalación de esquemas (tablas custom y CPTs base).

### Fase 1: IdentityAccess, Team/Staff, Booking Adapter
*   **Duración Est.:** 2 semanas
*   **Objetivos:**
    *   Módulo de Identidad y Acceso (creación y asignación de los 3 roles iniciales).
    *   Gestión de equipo y staff (`wp_qs_staff`).
    *   Adaptador de reservas (`LatepointGateway`).

### Fase 2: Bitácora, Finance
*   **Duración Est.:** 2-3 semanas
*   **Objetivos:**
    *   Módulo de Bitácora (registro de actividades y seguimientos).
    *   Módulo de Finanzas (control básico de pagos y gastos, usando `qs_payment` y `qs_expense`).
    *   Integración de Mutation Testing.

### Fase 3: ServicesCatalog, CRM/Leads
*   **Duración Est.:** 2 semanas
*   **Objetivos:**
    *   Módulo de catálogo de servicios (`qs_service`).
    *   CRM ligero y línea de tiempo de leads (`wp_qs_leads_timeline`).

### Fase 4: ContentWeb, Agents Registry
*   **Duración Est.:** 2 semanas
*   **Objetivos:**
    *   Gestión de contenido de la web pública (si es aplicable desde el Core).
    *   Registro de agentes.

### Fase 5+: CommunityOps, Meetings, Strategy
*   **Duración Est.:** Iterativo (cuando el equipo escale).
*   **Objetivos:** Expansión del sistema para soportar reuniones, estrategia y operaciones complejas.

# CLAUDE.md

Guidance for Claude Code (and other AI assistants) working in this repository.

## What this is

`qs-core` is a proprietary WordPress plugin (`qamilunastudio/qs-core`) that implements the
internal management system for **Qamiluna Studio** (a beauty/makeup studio in Chile), plus a
RAG chatbot (web + WhatsApp) backed by n8n, Qdrant, and third-party LLM providers.

The plugin is a **modular monolith** ("DDD lite"): one deployable WordPress plugin, with
internal modules organized by subdomain under `app/Modules/`, each following a
Domain / Application / Infrastructure / Interfaces layering.

There is a longer-term initiative (see `docs/adr/ADR-011-generic-wp-boilerplate.md` and
`docs/architecture/generic-wp-boilerplate-plan.md`) to extract the reusable core into a
generic WordPress plugin boilerplate. That is a separate, later effort — it does not change
current structure or conventions in this repo.

## Start here

- `README.md` — tech stack overview and implementation phases (Fase 0–5+).
- `docs/architecture/DECISIONS.md` — ADR summary table (architectural decisions 001–011).
- `docs/architecture/SYSTEM_SNAPSHOT.md` — the most detailed, current architecture doc:
  external services, chatbot/RAG data flow (with Mermaid diagrams), n8n workflows, DB
  migrations, WordPress options used by the chatbot. **Read this before touching the Agents
  module or anything chatbot/WhatsApp-related.**
- `docs/architecture/README.md` — pointers into `docs/adr/`.
- Per-module `README.md` files under `app/Modules/<Module>/README.md` (mostly stubs today —
  update them as modules gain real functionality).

## Architecture

### Layering (per module)

```
app/Modules/<Module>/
  Domain/           entities, value objects, repository interfaces, domain policies/exceptions
  Application/       commands, queries, command/query handlers, DTOs
  Infrastructure/    persistence ($wpdb/CPT repos), external gateways (n8n, Qdrant), WP-specific glue
  Interfaces/        REST controllers, WP-CLI commands, admin pages, hooks
  <Module>ServiceProvider.php   registers module DI bindings, hookables, activation hooks
```

- `app/Core/` — framework-agnostic-ish plugin core: bootstrap, DI container, config, hooks,
  events, logging, errors, security, WP versioning/migrations. **Must stay generic** — see
  the enforced rule below.
- `app/Shared/` — cross-module building blocks: `Bus/` (Command/Query bus), `ValueObjects/`
  (`Money`, `DateRange`, `UserId`, `ServiceId`), `Domain/` (`Entity`, `AggregateRoot`,
  `ValueObject` base classes), `Testing/` (`TestCase`, `WpTestCase`).
- `app/Interfaces/Rest/` — top-level system REST controllers (health/version), not tied to a
  module.
- `app/UI/Admin/` — React 18 admin dashboard embedded in `wp-admin` (`@wordpress/scripts`
  build, Zustand state, TailwindCSS, `@wordpress/api-fetch` REST client).

### Enforced architecture rules (tests, not just convention)

`tests/Architecture/LayerRulesTest.php` runs as part of the normal PHPUnit suite and will
**fail the build** if violated:

1. **Domain layer purity**: no file under any `Modules/*/Domain/` directory may reference
   `$wpdb`, `register_rest_route`, `add_action`, `add_filter`, an `Infrastructure\` or
   `Wordpress\` namespace, or any `WP_*` global/class. Domain code must have zero WordPress
   or infrastructure knowledge.
2. **Core genericity**: no file under `app/Core/` may contain the strings `qs-core`,
   `QS Core`, `Qamiluna`, `qamiluna`, or `qs_` — the core must stay product-agnostic (this
   supports the boilerplate-extraction effort above).

When adding code to `Domain/` or `Core/`, keep these constraints in mind up front rather than
discovering the failure in CI.

### Persistence strategy (ADR-005)

- **Custom Post Types**: `qs_bitacora`, `qs_service`, `qs_payment`, `qs_expense` — used for
  operational/content-like data.
- **Custom tables** (via `database/migrations/`): `wp_qs_staff`, `wp_qs_staff_roles`,
  `wp_qs_booking_snapshots`, `wp_qs_finance_entries`, `wp_qs_leads_timeline`,
  `wp_qs_audit_log`, `wp_qs_service_costs`, `wp_qs_chat_log`, `wp_qs_bookings`,
  `wp_qs_sheet_events` — used for relational/critical data. Migrations are numbered
  (`0001_...` … `0012_...`) and run through `QS\Core\Versioning\MigrationRunner`.
- **LatePoint integration**: read-only, via a `$wpdb` adapter encapsulated in
  `LatepointGateway` / `WpdbLatepointRepository` (Booking module) — never touch LatePoint
  tables directly outside that adapter.
- **Caching**: WordPress Transients only (no Redis/Memcached).

### API

- Native **WordPress REST API** under `/wp-json/qs/v1/*`. Routes are declared centrally in
  `config/routes/rest.php` (mapping `route → controller → action → permission_callback`) and
  `config/routes/admin.php`; there is no separate router/framework.
- Auth: WP Application Passwords (no custom auth plugin).
- Versioning: bump to `v2` in the namespace only when breaking the contract — don't add
  ad hoc breaking changes to `v1`.
- `docs/api/README.md` and `app/UI/RestDocs/openapi.yaml` document the surface — keep the
  OpenAPI spec in sync when routes change.

### Dependency injection

- PHP-DI (`php-di/php-di`) container, built by `QS\Core\Container\ContainerBuilder`.
- Bindings come from `QS\Core\Container\ServiceProvider::definitions()`
  (loaded via `config/di.php`), aggregating each module's `<Module>ServiceProvider`.
- `PluginBootstrapper` (`app/Core/Bootstrap/PluginBootstrapper.php`) wires activation,
  deactivation, and the `plugins_loaded` boot sequence: build container → register error
  handler → run pending migrations → load `ModuleRegistry` → register CPTs/REST routes →
  register all hookables (core + module) via `HookLoader`.
- Modules declare hookables/activation hooks by implementing `HookableInterface` /
  `ActivationHookInterface` and exposing them through `ServiceProvider::hookables()` /
  `::activationHooks()`.

### Command/Query separation

- `app/Shared/Bus/{Command,Query}Bus.php` + `{Command,Query}HandlerInterface` implement a
  simple CQRS-style bus. Application-layer use cases are `Command`/`Query` objects handled by
  a single `*Handler` class each (see `app/Modules/*/Application/{Command,Query}Handler/`).
  Follow this pattern for new use cases rather than putting logic directly in controllers.

### Modules (`app/Modules/`)

| Module | Purpose |
|---|---|
| `Agents` | Chatbot RAG (web + WhatsApp), n8n/Qdrant gateways, reindexing — most actively developed module. See `SYSTEM_SNAPSHOT.md`. |
| `Booking` | Read-mostly adapter over LatePoint reservations (`wpdb`), MUA agenda, sheet-events sync with Google Sheets. |
| `Finance` | Payments, expenses, margins; CPT-backed, monthly CSV export. |
| `Bitacora` | Service/field-visit logbook; CPT-backed, full REST CRUD. |
| `Team` | Staff/MUAs, availability (`wp_qs_staff`). |
| `ServicesCatalog` | Service catalog sourced from LatePoint (read-only). |
| `IdentityAccess` | Custom WP roles (`qs_admin`, `qs_coordinadora`, `qs_staff`) and access policies. |
| `Setup` | Site provisioning (menus, pages, options, permalinks), WP-CLI commands, agent status checks. |
| `CRM`, `CommunityOps`, `ContentWeb`, `Meetings`, `Strategy` | Placeholder modules (README-only), planned for later phases (Fase 3–5+). |

### Roles

Three operational WordPress roles (ADR-007): `qs_admin` (full access), `qs_coordinadora`
(operational management), `qs_staff` (own agenda/bitácora only). Enforced via
`IdentityAccess` module policies and `config/capabilities/roles.php`.

## External integrations (see SYSTEM_SNAPSHOT.md for full detail)

- **n8n** (self-hosted on Railway) — orchestrates chatbot RAG, ingestion, and WhatsApp
  routing via webhooks. Workflows live as JSON in `infrastructure/n8n/` and are synced to
  production by `tools/n8n/sync/sync_workflows.js` (CI job `deploy_n8n`).
- **Qdrant Cloud** — vector store for RAG (`wordpress_context` collection).
- **Hugging Face** / **OpenRouter** — embeddings and LLM inference for the chatbot.
- **Evolution API** (unofficial WhatsApp gateway, Render) + **Meta WhatsApp Business API**
  (official, critical messages only) — hybrid WhatsApp routing.
- **LatePoint** — existing WP booking plugin; read-only integration.
- **Google Sheets** — bitácora sync via `SheetEventsController` / `SheetEventRepository`.

When changing anything chatbot- or WhatsApp-related, check whether the corresponding n8n
workflow JSON in `infrastructure/n8n/` also needs updating, and whether the change affects
the WhatsApp kill switch behavior described in `SYSTEM_SNAPSHOT.md`.

## Development workflow

### Setup

```bash
composer install         # installs deps AND registers the pre-commit git hook
```

`post-install-cmd` / `post-update-cmd` run `composer run setup:githooks`
(`tools/git-hooks/install.php`), which points git at `.githooks/` (`pre-commit`,
`post-checkout`). The pre-commit hook validates project structure — on non-Windows hosts it
needs PowerShell (`pwsh`) available; if it's missing, the hook fails with an explicit message
telling you to install it. On Windows without `composer`/`php` on `PATH`, install manually via
`tools/git-hooks/install.ps1`.

### Common commands (composer scripts)

```bash
composer run test               # PHPUnit (Unit, Integration, Contract, Architecture suites)
composer run analyze            # PHPStan, level 8, config/quality/phpstan.neon
composer run format             # PHP CS Fixer, config/quality/.php-cs-fixer.dist.php
composer run validate:structure # enforce config/quality/project-structure.json (allowed root entries, restricted paths)
composer run package            # validate:structure + build dist/qs-core.zip via tools/package-plugin.php
```

Run these directly with `vendor/bin/...` if you need dry-run/verbose flags not exposed by the
composer scripts (e.g. `vendor/bin/php-cs-fixer fix --dry-run --diff`).

### Tests

- `phpunit.xml.dist` defines four suites: `Unit`, `Integration`, `Contract`, `Architecture`
  (all under `tests/`), bootstrapped via `tests/Support/bootstrap.php`.
- WordPress functions are mocked with **Brain Monkey** in unit tests (`WP_Mock` also
  available). See `app/Shared/Testing/TestCase.php` / `WpTestCase.php` for base classes.
- `tests/Architecture/LayerRulesTest.php` enforces the layering rules above — treat failures
  here as architecture violations to fix, not tests to loosen.
- `tests/Contract/` verifies REST endpoint schemas/behavior; `tests/Integration/` covers
  repositories and migrations against a real/mocked `$wpdb`.
- Mutation testing (Infection) currently targets **Booking** and **Finance** domains only
  (`config/quality/infection-booking.json`, `infection-finance.json`) and runs only on pull
  requests in CI, not on every push.
- Coverage target: Domain/Application code in Fase 1 modules > 80%.

### Static analysis & style

- PHPStan level 8 (`config/quality/phpstan.neon`), scanning `app/` and `tests/`, with
  WordPress stubs loaded (`php-stubs/wordpress-stubs`) so WP globals/functions type-check.
- PHP CS Fixer config at `config/quality/.php-cs-fixer.dist.php`; there's also a
  `config/quality/phpcs.xml` (WPCS-style ruleset) — check which one CI actually enforces
  before assuming both run automatically (CI currently only runs PHP CS Fixer + PHPStan +
  PHPUnit; `phpcs.xml` looks configured for manual/optional use).
- All new PHP files use `declare(strict_types=1);` and PSR-4 autoloading (`QS\` → `app/`,
  `QS\Tests\` → `tests/`).

### Project structure validation

`composer run validate:structure` (backed by
`tools/qa/structure/validate-project-structure.php`, config in
`config/quality/project-structure.json`) enforces:

- Only a fixed allow-list of entries may exist at the repo root (see the JSON for the exact
  list — don't add new top-level files/dirs without updating it).
- `app/` may only contain `Core`, `Interfaces`, `Modules`, `Shared`, `UI` subdirectories, no
  loose files at its root.
- `docs/` is restricted to a fixed set of subdirectories and file types.
- `infrastructure/n8n/` and `tools/n8n/` are restricted to specific file types/subfolders
  (workflow manifests, docs, ops scripts — no arbitrary code dumped in there).

This check runs both locally (pre-commit hook) and in CI, before `quality` and `package`
jobs — if you add new top-level files/directories, update
`config/quality/project-structure.json` in the same change or CI will fail.

### Frontend (admin dashboard)

- Lives under `app/UI/Admin/Assets/` (React 18 + `@wordpress/scripts` + Zustand +
  TailwindCSS), enqueued into `wp-admin` via `wp_enqueue_script`. Has its own
  `package.json` — run npm commands from that directory, not the repo root.
- `app/UI/Admin/{Components,Hooks,Pages,Stores}/` are currently README stubs describing
  intended structure for future admin UI work.

### CI/CD (`.github/workflows/ci.yml`)

On every push/PR, job `quality`: `validate:structure` → PHP CS Fixer (dry-run) → PHPStan →
PHPUnit (with coverage). On PRs only, also runs Infection mutation testing for Booking and
Finance.

On push to `main` only, after `quality` passes:
- job `package`: rebuilds with `--no-dev`, validates structure again, packages via
  `tools/package-plugin.php` into `dist/qs-core/` + `dist/qs-core.zip`, uploads as a CI
  artifact, then deploys via FTP to cPanel (`tools/deploy/ftp-sync.php`, incremental sync,
  state tracked in `.ftp-deploy-sync-state.json` on the remote). Requires `FTP_SERVER`,
  `FTP_USERNAME`, `FTP_PASSWORD` secrets.
- job `deploy_n8n`: runs `tools/n8n/sync/sync_workflows.js` to upsert the 4 n8n workflows in
  production.

There is **no containerized deploy** — production is shared cPanel hosting, deployed by
pushing files over FTP. Keep that constraint in mind when suggesting infra changes (e.g. no
Docker-based deploy suggestions for this plugin itself; Docker is fine for the *n8n*/RAG
infra which already runs on Railway/Render).

Two additional scheduled workflows: `chatbot-health.yml` (every 30 min, opens/updates a
GitHub issue labeled `ops-alert` on failure) and `chatbot-backup.yml` (daily, snapshots
health + n8n workflows + Railway Postgres dump as a 30-day-retention artifact).

### Local scripts / manual testing

- PowerShell helpers in `tools/http/` call the live REST API using `QS_API_BASE`,
  `QS_API_USER`, `QS_API_PASS` from `.env` (e.g. `Get-QsBookingsToday.ps1`,
  `Invoke-QsApi.ps1 -Path 'health'`, `Invoke-QsSetup.ps1`, `Get-QsAgentStatus.ps1`).
- If WP-CLI is available on the host: `wp qs setup`, `wp qs status`, `wp qs reindex`,
  `wp qs chat "..."` (registered via `Setup\Interfaces\Cli`). Fallback without WP-CLI:
  `wp eval-file tools/setup/wp-setup.php` or direct PHP invocation with `--wp-load`.
- `tools/n8n/`, `tools/chatbots/` — Node/PowerShell scripts for managing n8n workflows and
  chatbot ops (export, sync, health checks, E2E WhatsApp tests) directly against the
  Railway/Render infra. These operate on **live/production-adjacent services** — treat them
  as operational tooling, not test fixtures; don't run mutating ones against production
  without explicit confirmation.

## Conventions checklist for new code

- Put new domain logic in `Domain/`, keep it free of WordPress/infra references (enforced by
  `LayerRulesTest`).
- Expose new use cases as a `Command`/`Query` + matching `*Handler`, not as logic embedded in
  a controller.
- Add new REST endpoints to `config/routes/rest.php` (and admin-only ones to
  `config/routes/admin.php`), with an explicit `permission_callback` — don't wire routes any
  other way. Update `app/UI/RestDocs/openapi.yaml` and `docs/api/README.md` alongside.
- New custom tables go through a new numbered file in `database/migrations/`, run by
  `MigrationRunner`. New CPTs are registered in `config/wordpress/post-types.php` /
  `PostTypeRegistrar`.
- New module: create `app/Modules/<Name>/` following the layering above, plus a
  `<Name>ServiceProvider`, and register it so `ServiceProvider::definitions()` picks it up.
  Add a `config/modules/<name>.php` if it needs module-specific config.
  Don't add anything to the repo root — `validate:structure` will reject it.
  Keep secrets, credentials, and site-specific values out of code — use env vars / WP options
  (see the `qs_*` wp_options table in `SYSTEM_SNAPSHOT.md`), never hardcode them.
- Match existing code style: `declare(strict_types=1)`, constructor property promotion with
  `readonly` where applicable, PHP 8.1+ enums for fixed value sets, PSR-4 namespaces mirroring
  directory structure exactly (`QS\Modules\<Module>\...`).
- This is a Chilean/Spanish-market product: user-facing strings, docs, and comments are
  predominantly in Spanish — follow the existing language of the file/module you're editing
  rather than switching to English mid-file.

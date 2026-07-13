# Project: QS Manager V2 Sincronizar Button & Audit
# Scope: Milestone 1

## Architecture
- Module/package boundaries: Web UI (public/index.php, WebController), Sheet Sync (SheetSyncController, PostgresSheetReplicaImporter).
- Data flow: UI Button -> POST /api/v1/sync -> SheetSyncController -> PostgresSheetReplicaImporter -> DB. JS handles the loading state, success/error, and reloads DOM or refetches data.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | SyncButtonAudit | Conduct UI audit, implement Sync button, quick wins, ensure tests pass. | none | PLANNED |

## Interface Contracts
### WebUI ↔ API
- Web UI sends fetch request to sync endpoint.
- Web UI handles response and refreshes tables dynamically.

## Code Layout
- public/ (frontend assets)
- app/Interfaces/Http/ (controllers)
- app/Infrastructure/Sheets/ (sync logic)
- tests/ (PHPUnit tests)

$ErrorActionPreference = "Stop"

Write-Host "Running pending migrations..." -ForegroundColor Cyan

# Run the 0006 migration which alters the existing qs_sync_runs table
docker compose exec -T db psql -U qs_user -d qs_manager_v2 -f /docker-entrypoint-initdb.d/0006_async_sync_heartbeat.sql

Write-Host "Migrations completed." -ForegroundColor Green

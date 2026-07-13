param(
    [string] $Container = "qs-manager-v2-db",
    [string] $Database = "qs_manager_v2",
    [string] $User = "qs_user"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$migrations = Get-ChildItem -Path (Join-Path $root "database/migrations") -Filter "*.sql" | Sort-Object Name

foreach ($migration in $migrations) {
    Write-Host "Applying $($migration.Name)"
    Get-Content -Path $migration.FullName | docker exec -i $Container psql -U $User -d $Database
}

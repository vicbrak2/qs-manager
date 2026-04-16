#Requires -Version 5.1
param(
    [string] $Site = 'qamiluna',
    [string] $ConfigPath = '',
    [string] $EnvFile = '',
    [string] $RootEnvFile = '',
    [string] $PostgresImage = 'postgres:16-alpine'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'ChatbotOps.psm1') -Force

$ctx = New-ChatbotOpsContext -Site $Site -ConfigPath $ConfigPath -EnvFile $EnvFile -RootEnvFile $RootEnvFile
if ($ctx.RailwayApiToken -eq '') {
    throw 'Falta RAILWAY_API_TOKEN para consultar variables de Railway.'
}

Remove-Item Env:RAILWAY_TOKEN -ErrorAction SilentlyContinue
$env:RAILWAY_API_TOKEN = $ctx.RailwayApiToken

$service = [string]$ctx.Site.railwayPostgresService
$environment = [string]$ctx.Site.railwayEnvironment
$vars = railway variable list --service $service --environment $environment --json | ConvertFrom-Json

$hostName = [string]$vars.RAILWAY_TCP_PROXY_DOMAIN
$port = [string]$vars.RAILWAY_TCP_PROXY_PORT
$database = [string]$vars.POSTGRES_DB
$user = [string]$vars.POSTGRES_USER
$password = [string]$vars.POSTGRES_PASSWORD

if ($hostName -eq '' -or $port -eq '' -or $database -eq '' -or $user -eq '' -or $password -eq '') {
    throw "Variables incompletas para Postgres en Railway service '$service'."
}

$target = New-ChatbotBackupPath -Context $ctx -Kind 'postgres'
$dumpFile = Join-Path $target "$($ctx.Site.id)-postgres.dump"
$manifestFile = Join-Path $target 'manifest.json'

$pgDump = Get-Command pg_dump -ErrorAction SilentlyContinue
if ($pgDump) {
    $env:PGPASSWORD = $password
    & $pgDump.Source --host $hostName --port $port --username $user --dbname $database --format custom --no-owner --no-acl --file $dumpFile
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
} else {
    $docker = Get-Command docker -ErrorAction SilentlyContinue
    if (-not $docker) {
        throw 'No se encontro pg_dump ni docker. Instala PostgreSQL client tools o Docker para generar dumps.'
    }

    $outputDir = Split-Path -Parent $dumpFile
    $outputName = Split-Path -Leaf $dumpFile
    & $docker.Source run --rm `
        -e "PGPASSWORD=$password" `
        -v "${outputDir}:/backup" `
        $PostgresImage `
        pg_dump --host $hostName --port $port --username $user --dbname $database --format custom --no-owner --no-acl --file "/backup/$outputName"
}

if (-not (Test-Path -LiteralPath $dumpFile -PathType Leaf)) {
    throw "No se genero el dump esperado: $dumpFile"
}

$manifest = [pscustomobject]@{
    site = $ctx.Site.id
    railwayService = $service
    railwayEnvironment = $environment
    database = $database
    host = $hostName
    port = $port
    createdAt = (Get-Date).ToString('o')
    dumpFile = (Split-Path -Leaf $dumpFile)
    bytes = (Get-Item -LiteralPath $dumpFile).Length
}

$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $manifestFile -Encoding UTF8
Write-Host "Backup Postgres generado en $dumpFile" -ForegroundColor Green

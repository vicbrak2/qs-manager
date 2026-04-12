#Requires -Version 5.1
<#
.SYNOPSIS
    Abre un tunnel cloudflared para Evolution API (localhost:8080),
    detecta la URL pública automáticamente y la actualiza en Railway.

    Uso:
      .\Start-EvolutionTunnel.ps1 [-RailwayToken "tu_token"]

    Si no pasas -RailwayToken, lee la variable de entorno RAILWAY_TOKEN.
    El token se obtiene en: https://railway.com/account/tokens

    Deja esta ventana abierta mientras el bot esté activo.
    Ctrl+C para detener el tunnel (Railway quedará con la última URL).

.NOTES
    Requiere cloudflared.exe en PATH o en la misma carpeta.
    Descarga: https://github.com/cloudflare/cloudflared/releases/latest
#>
param(
    [string]$RailwayToken = $env:RAILWAY_TOKEN,
    [string]$ProjectId    = '763a4ec5-1753-42a7-98eb-4c4e50c47ea3',
    [string]$ServiceId    = '7d61eb0d-6161-4881-a763-c874827d1c68',
    [string]$EnvId        = '8f508df4-e518-4be4-9991-fec1eedcd399',
    [int]$Port            = 8080
)

$ErrorActionPreference = 'Stop'

$varTmpDir = Join-Path $PSScriptRoot '..\..\var\tmp'
if (-not (Test-Path $varTmpDir)) { New-Item -ItemType Directory -Path $varTmpDir -Force | Out-Null }
$logFile    = Join-Path $varTmpDir 'cloudflared-evolution.log'
$urlFile    = Join-Path $varTmpDir 'evolution-tunnel-url.txt'

# ── Buscar cloudflared ────────────────────────────────────────
$cf = Get-Command 'cloudflared' -ErrorAction SilentlyContinue
if (-not $cf) {
    $local = Join-Path $PSScriptRoot 'cloudflared.exe'
    if (Test-Path $local) { $cfExe = $local }
    else {
        Write-Host "[ERROR] cloudflared no encontrado." -ForegroundColor Red
        Write-Host "Descarga desde: https://github.com/cloudflare/cloudflared/releases/latest" -ForegroundColor Gray
        Write-Host "Coloca cloudflared.exe en: $PSScriptRoot" -ForegroundColor Gray
        exit 1
    }
} else { $cfExe = $cf.Source }

# ── Función para actualizar Railway ──────────────────────────
function Update-RailwayEvolutionUrl {
    param([string]$PublicUrl)

    if ([string]::IsNullOrWhiteSpace($RailwayToken)) {
        Write-Host ""
        Write-Host "[AVISO] RAILWAY_TOKEN no definido. Actualiza manualmente en Railway:" -ForegroundColor Yellow
        Write-Host "  EVOLUTION_API_BASE_URL = $PublicUrl" -ForegroundColor Cyan
        Write-Host "  O corre: `$env:RAILWAY_TOKEN = 'tu_token'" -ForegroundColor Gray
        Write-Host "           .\Start-EvolutionTunnel.ps1" -ForegroundColor Gray
        return
    }

    $mutation = @"
mutation {
  variableUpsert(input: {
    projectId: "$ProjectId"
    serviceId: "$ServiceId"
    environmentId: "$EnvId"
    name: "EVOLUTION_API_BASE_URL"
    value: "$PublicUrl"
  })
}
"@
    try {
        $body = @{ query = $mutation } | ConvertTo-Json
        $resp = Invoke-RestMethod -Method Post `
            -Uri 'https://backboard.railway.com/graphql/v2' `
            -Headers @{
                'Authorization' = "Bearer $RailwayToken"
                'Content-Type'  = 'application/json'
            } `
            -Body $body -TimeoutSec 15

        if ($resp.data.variableUpsert -eq $true) {
            Write-Host ""
            Write-Host "╔══════════════════════════════════════════════════════════╗" -ForegroundColor Green
            Write-Host "  Railway actualizado: EVOLUTION_API_BASE_URL = $PublicUrl" -ForegroundColor Green
            Write-Host "  n8n recibirá el nuevo valor en el próximo deploy/restart." -ForegroundColor Green
            Write-Host "╚══════════════════════════════════════════════════════════╝" -ForegroundColor Green
        } else {
            Write-Host "[AVISO] Railway respondió pero no confirmó. Verifica manualmente." -ForegroundColor Yellow
            Write-Host ($resp | ConvertTo-Json) -ForegroundColor Gray
        }
    } catch {
        Write-Host "[ERROR actualizando Railway] $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "Actualiza manualmente: EVOLUTION_API_BASE_URL = $PublicUrl" -ForegroundColor Yellow
    }
}

# ── Banner ────────────────────────────────────────────────────
Write-Host ""
Write-Host "══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Evolution API Tunnel  (localhost:$Port)     " -ForegroundColor Cyan
Write-Host "══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "Iniciando tunnel cloudflared..." -ForegroundColor Yellow
if ([string]::IsNullOrWhiteSpace($RailwayToken)) {
    Write-Host "[!] RAILWAY_TOKEN no definido — URL deberá actualizarse manualmente." -ForegroundColor Yellow
} else {
    Write-Host "[ok] RAILWAY_TOKEN presente — se actualizará Railway automáticamente." -ForegroundColor Green
}
Write-Host ""

'' | Out-File -FilePath $logFile -Encoding utf8
$urlDetected = $false

# ── Iniciar tunnel y parsear output ─────────────────────────
& $cfExe tunnel --url "http://localhost:$Port" 2>&1 | ForEach-Object {
    $line = [string]$_

    # Detectar URL del tunnel
    if (-not $urlDetected -and $line -match 'https://[\w\-]+\.trycloudflare\.com') {
        $tunnelUrl = $Matches[0].Trim()
        $urlDetected = $true

        Write-Host ""
        Write-Host "╔══════════════════════════════════════════════════════════╗" -ForegroundColor Green
        Write-Host "  TUNNEL ACTIVO: $tunnelUrl" -ForegroundColor Green
        Write-Host "╚══════════════════════════════════════════════════════════╝" -ForegroundColor Green
        Write-Host ""

        $tunnelUrl | Out-File -FilePath $urlFile -Encoding utf8

        # Actualizar Railway
        Update-RailwayEvolutionUrl -PublicUrl $tunnelUrl

        Write-Host ""
        Write-Host "Deja esta ventana abierta mientras uses el bot." -ForegroundColor Yellow
        Write-Host "Ctrl+C para detener." -ForegroundColor Gray
        Write-Host ""
    }

    # Log + color
    $line | Add-Content -Path $logFile
    if ($line -match 'ERR|error') {
        Write-Host $line -ForegroundColor Red
    } elseif ($line -match 'Tunnel connection|Registered') {
        Write-Host $line -ForegroundColor Green
    } elseif ($line -match 'INF') {
        Write-Host $line -ForegroundColor Gray
    } else {
        Write-Host $line
    }
}

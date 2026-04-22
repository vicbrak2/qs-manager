#Requires -Version 5.1
<#
.SYNOPSIS
    Prueba el flujo E2E de salida: n8n hybrid-whatsapp → Evolution → WhatsApp.
    Envía un mensaje de prueba al número que indiques sin pasar por el inbound.

    Uso: .\Test-WhatsAppE2E.ps1 -Phone "5491155551234" [-Message "Texto personalizado"]
    Variables requeridas:
        N8N_BASE_URL   (ej: https://n8n.tu-railway.app)
        EVOLUTION_API_BASE_URL
        EVOLUTION_INSTANCE_NAME
        EVOLUTION_API_KEY

    Param Phone: número en formato internacional sin + ni espacios
                 ej: 5491155551234  (Argentina: 54 + 9 + área + número)
#>
param(
    [Parameter(Mandatory)]
    [string]$Phone,

    [string]$Message = "Hola! Este es un mensaje de prueba E2E del bot de Qamiluna Studio. Si lo recibes, el flujo WhatsApp funciona correctamente."
)

$ErrorActionPreference = 'Stop'

function Resolve-Env {
    param([string]$Name, [string]$Default = '')
    $v = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($v)) { return $Default }
    return $v.Trim()
}

$n8nBase       = (Resolve-Env 'N8N_BASE_URL').TrimEnd('/')
$evolutionBase = (Resolve-Env 'EVOLUTION_API_BASE_URL').TrimEnd('/')
$instanceName  = Resolve-Env 'EVOLUTION_INSTANCE_NAME'
$apiKey        = Resolve-Env 'EVOLUTION_API_KEY'

Write-Host "`n══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Test E2E WhatsApp — Flujo de salida         " -ForegroundColor Cyan
Write-Host "══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Destino : $Phone"
Write-Host "  Mensaje : $Message`n"

# ── PASO 1: Probar vía router híbrido n8n ─────────────────────────────────
Write-Host "PASO 1: Enviando via hybrid-whatsapp en n8n..." -ForegroundColor Yellow

if ($n8nBase -ne '') {
    $routerUrl  = "$n8nBase/webhook/hybrid-whatsapp"
    $routerBody = @{
        phone      = $Phone
        text       = $Message
        esCritico  = $false
    } | ConvertTo-Json

    try {
        $resp = Invoke-RestMethod -Method Post `
            -Uri $routerUrl `
            -Body $routerBody `
            -ContentType 'application/json' `
            -TimeoutSec 20
        Write-Host "  [OK] Router respondio:" -ForegroundColor Green
        $resp | ConvertTo-Json | Write-Host
    } catch {
        Write-Host "  [FAIL] Router error: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "  -> Comprueba que WhatsApp Hybrid Router este activo en n8n" -ForegroundColor Gray
    }
} else {
    Write-Host "  [SKIP] N8N_BASE_URL no definido" -ForegroundColor Gray
}

# ── PASO 2: Probar directamente contra Evolution (bypass n8n) ─────────────
Write-Host "`nPASO 2: Enviando directamente a Evolution API..." -ForegroundColor Yellow

if ($evolutionBase -ne '' -and $instanceName -ne '' -and $apiKey -ne '') {
    $evoUrl  = "$evolutionBase/message/sendText/$instanceName"
    $evoBody = @{
        number      = $Phone
        text        = "[DIRECTO] $Message"
        delay       = 1200
    } | ConvertTo-Json -Depth 3

    $evoHeaders = @{
        apikey         = $apiKey
        'Content-Type' = 'application/json'
    }

    try {
        $evoResp = Invoke-RestMethod -Method Post `
            -Uri $evoUrl `
            -Headers $evoHeaders `
            -Body $evoBody `
            -TimeoutSec 20
        Write-Host "  [OK] Evolution respondio:" -ForegroundColor Green
        $evoResp | ConvertTo-Json | Write-Host
        Write-Host "`n  Si recibes el mensaje '[DIRECTO]' en WhatsApp," -ForegroundColor Cyan
        Write-Host "  Evolution funciona. Si no llega el del PASO 1," -ForegroundColor Cyan
        Write-Host "  el problema esta en n8n (variables o workflow)." -ForegroundColor Cyan
    } catch {
        Write-Host "  [FAIL] Evolution directo error: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "  -> Verifica EVOLUTION_API_KEY y que la instancia este conectada." -ForegroundColor Gray
    }
} else {
    Write-Host "  [SKIP] Variables de Evolution no definidas" -ForegroundColor Gray
}

Write-Host "`n══════════════════════════════════════════════`n" -ForegroundColor Cyan

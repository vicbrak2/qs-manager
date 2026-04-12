#Requires -Version 5.1
<#
.SYNOPSIS
    Configura el webhook de Evolution API para que apunte al
    workflow WhatsApp Inbound Bridge en n8n.

    Uso: .\Set-EvolutionWebhook.ps1
    Variables requeridas:
        EVOLUTION_API_BASE_URL   (ej: https://evolution.tu-railway.app)
        EVOLUTION_INSTANCE_NAME  (ej: qamiluna-test)
        EVOLUTION_API_KEY
        N8N_BASE_URL             (ej: https://n8n.tu-railway.app)
#>
$ErrorActionPreference = 'Stop'

function Resolve-Env {
    param([string]$Name, [string]$Default = '')
    $v = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($v)) { return $Default }
    return $v.Trim()
}

$evolutionBase = (Resolve-Env 'EVOLUTION_API_BASE_URL').TrimEnd('/')
$instanceName  = Resolve-Env 'EVOLUTION_INSTANCE_NAME'
$apiKey        = Resolve-Env 'EVOLUTION_API_KEY'
$n8nBase       = (Resolve-Env 'N8N_BASE_URL').TrimEnd('/')

if ($evolutionBase -eq '' -or $instanceName -eq '' -or $apiKey -eq '') {
    throw 'Define EVOLUTION_API_BASE_URL, EVOLUTION_INSTANCE_NAME y EVOLUTION_API_KEY.'
}
if ($n8nBase -eq '') {
    throw 'Define N8N_BASE_URL (URL publica de tu n8n en Railway).'
}

$webhookUrl = "$n8nBase/webhook/evolution-inbound"

Write-Host "`nConfigurando webhook de Evolution" -ForegroundColor Cyan
Write-Host "  Instancia : $instanceName"
Write-Host "  Evolution : $evolutionBase"
Write-Host "  Webhook   : $webhookUrl`n"

$headers = @{
    apikey         = $apiKey
    'Content-Type' = 'application/json'
    Accept         = 'application/json'
}

# Payload de webhook para Evolution v2
$body = @{
    webhook = @{
        enabled  = $true
        url      = $webhookUrl
        webhookByEvents = $false
        webhookBase64   = $false
        events   = @(
            'MESSAGES_UPSERT'
        )
    }
} | ConvertTo-Json -Depth 5

try {
    $resp = Invoke-RestMethod -Method Post `
        -Uri "$evolutionBase/webhook/set/$instanceName" `
        -Headers $headers `
        -Body $body `
        -TimeoutSec 15
    Write-Host "Webhook configurado OK:" -ForegroundColor Green
    $resp | ConvertTo-Json -Depth 3 | Write-Host
} catch {
    # Algunos builds de Evolution usan PUT o endpoint diferente
    Write-Warning "POST falló ($($_.Exception.Message)). Intentando con endpoint alternativo..."
    try {
        $resp2 = Invoke-RestMethod -Method Put `
            -Uri "$evolutionBase/webhook/set/$instanceName" `
            -Headers $headers `
            -Body $body `
            -TimeoutSec 15
        Write-Host "Webhook configurado OK (PUT):" -ForegroundColor Green
        $resp2 | ConvertTo-Json -Depth 3 | Write-Host
    } catch {
        throw "No se pudo configurar el webhook: $($_.Exception.Message)"
    }
}

Write-Host "`nVerificando configuracion actual..." -ForegroundColor Cyan
try {
    $current = Invoke-RestMethod -Method Get `
        -Uri "$evolutionBase/webhook/find/$instanceName" `
        -Headers $headers -TimeoutSec 10
    Write-Host "Webhook activo:" -ForegroundColor Green
    $current | ConvertTo-Json -Depth 3 | Write-Host
} catch {
    Write-Warning "No se pudo leer la config actual: $($_.Exception.Message)"
}

#Requires -Version 5.1
<#
.SYNOPSIS
    Sube/actualiza el workflow WhatsApp Inbound Bridge a n8n (Railway o local)
    y lo activa.

    Uso: .\Sync-InboundWorkflow.ps1
    Variables requeridas:
        N8N_BASE_URL   (ej: https://n8n.tu-railway.app)
        N8N_API_KEY    (API key de n8n)
#>
$ErrorActionPreference = 'Stop'

function Resolve-Env {
    param([string]$Name, [string]$Default = '')
    $v = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($v)) { return $Default }
    return $v.Trim()
}

$n8nBase = (Resolve-Env 'N8N_BASE_URL').TrimEnd('/')
$n8nKey  = Resolve-Env 'N8N_API_KEY'

if ($n8nBase -eq '' -or $n8nKey -eq '') {
    throw 'Define N8N_BASE_URL y N8N_API_KEY en tu entorno antes de correr este script.'
}

$headers = @{
    'X-N8N-API-KEY' = $n8nKey
    'Content-Type'  = 'application/json'
    'Accept'        = 'application/json'
}

# Ruta al workflow JSON (relativo a este script)
$scriptDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot    = Split-Path -Parent (Split-Path -Parent $scriptDir)
$workflowFile = Join-Path $repoRoot 'infrastructure\n8n\whatsapp_inbound_bridge_workflow.json'

if (-not (Test-Path $workflowFile)) {
    throw "No se encontro el archivo: $workflowFile"
}

$workflowJson = Get-Content $workflowFile -Raw
$workflowObj  = $workflowJson | ConvertFrom-Json
$workflowName = $workflowObj.name

Write-Host "Workflow a sincronizar: $workflowName" -ForegroundColor Cyan

# 1. Buscar si ya existe
Write-Host "Buscando workflow en n8n..."
$existing = $null
try {
    $list = Invoke-RestMethod -Method Get `
        -Uri "$n8nBase/api/v1/workflows" `
        -Headers $headers -TimeoutSec 15

    $items = if ($list.data) { $list.data } else { @($list) }
    $existing = $items | Where-Object { $_.name -eq $workflowName } | Select-Object -First 1
} catch {
    Write-Warning "No se pudo listar workflows: $($_.Exception.Message)"
}

# 2. Crear o actualizar
if ($existing) {
    $wfId = $existing.id
    Write-Host "Workflow existente (id=$wfId). Actualizando..." -ForegroundColor Yellow
    $updated = Invoke-RestMethod -Method Put `
        -Uri "$n8nBase/api/v1/workflows/$wfId" `
        -Headers $headers `
        -Body $workflowJson `
        -TimeoutSec 20
    Write-Host "Actualizado OK. id=$($updated.id)" -ForegroundColor Green
    $wfId = $updated.id
} else {
    Write-Host "Workflow nuevo. Creando..." -ForegroundColor Yellow
    $created = Invoke-RestMethod -Method Post `
        -Uri "$n8nBase/api/v1/workflows" `
        -Headers $headers `
        -Body $workflowJson `
        -TimeoutSec 20
    Write-Host "Creado OK. id=$($created.id)" -ForegroundColor Green
    $wfId = $created.id
}

# 3. Activar
Write-Host "Activando workflow id=$wfId..."
try {
    Invoke-RestMethod -Method Post `
        -Uri "$n8nBase/api/v1/workflows/$wfId/activate" `
        -Headers $headers -TimeoutSec 15 | Out-Null
    Write-Host "Workflow ACTIVO." -ForegroundColor Green
} catch {
    Write-Warning "No se pudo activar via API (puede ya estar activo): $($_.Exception.Message)"
}

Write-Host "`nURL del webhook inbound:" -ForegroundColor Cyan
Write-Host "  $n8nBase/webhook/evolution-inbound" -ForegroundColor White
Write-Host "`nUsa esta URL en la configuracion de webhook de Evolution." -ForegroundColor Yellow

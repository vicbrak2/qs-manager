#Requires -Version 5.1
<#
.SYNOPSIS
    Pre-checks obligatorios para el flujo E2E WhatsApp → n8n → WordPress → WhatsApp
    Uso: .\Check-E2EReadiness.ps1
    Variables requeridas (entorno o .env):
        EVOLUTION_API_BASE_URL   (ej: https://evolution.tu-railway.app)
        EVOLUTION_INSTANCE_NAME  (ej: qamiluna-test)
        EVOLUTION_API_KEY        (clave de Evolution)
        N8N_BASE_URL             (ej: https://n8n.tu-railway.app)
        N8N_API_KEY              (API key de n8n)
        WORDPRESS_URL            (ej: https://qamilunastudio.com)
#>
$ErrorActionPreference = 'Stop'
$results = @{}
$allOk = $true

function Write-Check {
    param([string]$Label, [bool]$Ok, [string]$Detail = '')
    $icon = if ($Ok) { '[OK]' } else { '[FAIL]' }
    $color = if ($Ok) { 'Green' } else { 'Red' }
    Write-Host "$icon $Label" -ForegroundColor $color
    if ($Detail) { Write-Host "     $Detail" -ForegroundColor Gray }
}

function Resolve-Env {
    param([string]$Name, [string]$Default = '')
    $v = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($v)) { return $Default }
    return $v.Trim()
}

# ── Variables ────────────────────────────────────────────────────────────────
$evolutionBase    = (Resolve-Env 'EVOLUTION_API_BASE_URL').TrimEnd('/')
$evolutionInst    = Resolve-Env 'EVOLUTION_INSTANCE_NAME'
$evolutionKey     = Resolve-Env 'EVOLUTION_API_KEY'
$n8nBase          = (Resolve-Env 'N8N_BASE_URL').TrimEnd('/')
$n8nKey           = Resolve-Env 'N8N_API_KEY'
$wpUrl            = (Resolve-Env 'WORDPRESS_URL' 'https://qamilunastudio.com').TrimEnd('/')

Write-Host "`n══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  E2E Readiness Check — WhatsApp Bot Flow    " -ForegroundColor Cyan
Write-Host "══════════════════════════════════════════════`n" -ForegroundColor Cyan

# ── 1. Variables presentes ───────────────────────────────────────────────────
Write-Host "1) Variables de entorno" -ForegroundColor Yellow
$varOk = @{
    EVOLUTION_API_BASE_URL   = $evolutionBase -ne ''
    EVOLUTION_INSTANCE_NAME  = $evolutionInst -ne ''
    EVOLUTION_API_KEY        = $evolutionKey  -ne ''
    N8N_BASE_URL             = $n8nBase       -ne ''
    N8N_API_KEY              = $n8nKey        -ne ''
    WORDPRESS_URL            = $wpUrl         -ne ''
}
foreach ($kv in $varOk.GetEnumerator()) {
    $ok = $kv.Value
    if (-not $ok) { $allOk = $false }
    Write-Check $kv.Key $ok
}

# ── 2. Evolution ─────────────────────────────────────────────────────────────
Write-Host "`n2) Evolution API" -ForegroundColor Yellow
try {
    if ($evolutionBase -eq '' -or $evolutionKey -eq '') {
        throw 'Variables faltantes'
    }
    $headers = @{ apikey = $evolutionKey; Accept = 'application/json' }
    $instances = Invoke-RestMethod -Method Get `
        -Uri "$evolutionBase/instance/fetchInstances" `
        -Headers $headers -TimeoutSec 15

    $arr = if ($instances -is [System.Array]) { $instances } else { @($instances) }
    Write-Check "Evolution responde" $true "$($arr.Count) instancia(s) encontrada(s)"

    if ($evolutionInst -eq '') { $evolutionInst = [string]$arr[0].name }
    $inst = $arr | Where-Object { [string]$_.name -eq $evolutionInst } | Select-Object -First 1

    if (-not $inst) {
        Write-Check "Instancia '$evolutionInst'" $false "No existe. Instancias: $($arr.name -join ', ')"
        $allOk = $false
    } else {
        $status = [string]$inst.connectionStatus
        $isOpen = $status -eq 'open'
        if (-not $isOpen) { $allOk = $false }
        Write-Check "Instancia '$evolutionInst'" $isOpen "connection_status=$status"
        $results['evolution_instance'] = $evolutionInst
        $results['evolution_status']   = $status
        $results['evolution_ready']    = $isOpen
    }
} catch {
    Write-Check "Evolution API" $false $_.Exception.Message
    $allOk = $false
}

# ── 3. n8n ───────────────────────────────────────────────────────────────────
Write-Host "`n3) n8n" -ForegroundColor Yellow
try {
    if ($n8nBase -eq '' -or $n8nKey -eq '') {
        throw 'Variables faltantes'
    }
    $n8nHeaders = @{ 'X-N8N-API-KEY' = $n8nKey; Accept = 'application/json' }
    $health = Invoke-WebRequest -Method Get `
        -Uri "$n8nBase/healthz" -TimeoutSec 10 -UseBasicParsing
    Write-Check "n8n healthz" ($health.StatusCode -eq 200) "HTTP $($health.StatusCode)"

    # Verificar workflows activos
    $wfs = Invoke-RestMethod -Method Get `
        -Uri "$n8nBase/api/v1/workflows?active=true" `
        -Headers $n8nHeaders -TimeoutSec 15

    $wfList = if ($wfs.data) { $wfs.data } else { @($wfs) }
    $wfNames = $wfList | ForEach-Object { $_.name }

    $required = @('WordPress RAG Chatbot', 'WhatsApp Hybrid Router', 'WhatsApp Inbound Bridge')
    foreach ($req in $required) {
        $found = $wfNames -contains $req
        if (-not $found) { $allOk = $false }
        Write-Check "Workflow: $req" $found (if (-not $found) { "NO está activo. Activos: $($wfNames -join ', ')" } else { "activo" })
    }
} catch {
    Write-Check "n8n" $false $_.Exception.Message
    $allOk = $false
}

# ── 4. WordPress Bot Endpoint ────────────────────────────────────────────────
Write-Host "`n4) WordPress /wp-json/qs/v1/agents/chat" -ForegroundColor Yellow
try {
    $testBody = '{"message":"ping","session_id":"readiness_check","channel":"whatsapp"}'
    $wpResp = Invoke-WebRequest -Method Post `
        -Uri "$wpUrl/wp-json/qs/v1/agents/chat" `
        -Body $testBody -ContentType 'application/json' `
        -TimeoutSec 20 -UseBasicParsing
    $wpOk = $wpResp.StatusCode -lt 400
    if (-not $wpOk) { $allOk = $false }
    Write-Check "WordPress bot endpoint" $wpOk "HTTP $($wpResp.StatusCode)"
} catch {
    $msg = $_.Exception.Message
    # 401/403 significa que existe pero requiere auth → aceptable
    if ($msg -match '401|403|Unauthorized|Forbidden') {
        Write-Check "WordPress bot endpoint" $true "Responde (auth requerida, normal) — $msg"
    } else {
        Write-Check "WordPress bot endpoint" $false $msg
        $allOk = $false
    }
}

# ── Resumen ───────────────────────────────────────────────────────────────────
Write-Host "`n══════════════════════════════════════════════" -ForegroundColor Cyan
if ($allOk) {
    Write-Host "  RESULTADO: TODO OK — Listo para E2E  " -ForegroundColor Green
} else {
    Write-Host "  RESULTADO: HAY PROBLEMAS — Revisar arriba" -ForegroundColor Red
}
Write-Host "══════════════════════════════════════════════`n" -ForegroundColor Cyan

# Output parseable para scripts
"evolution_ready=$($results['evolution_ready'])"
"evolution_status=$($results['evolution_status'])"
"all_ok=$allOk"

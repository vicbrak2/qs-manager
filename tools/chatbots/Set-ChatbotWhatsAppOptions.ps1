<#
.SYNOPSIS
    Lee o actualiza las opciones del WhatsApp Gateway del chatbot via REST API.

.DESCRIPTION
    Usa el endpoint /wp-json/qs/v1/agents/whatsapp-options del plugin QS Core.
    Requiere las variables QS_API_BASE, QS_API_USER y QS_API_PASS en .env o entorno.

.PARAMETER Site
    ID del sitio definido en config/chatbots/sites.json. Default: qamiluna.

.PARAMETER WebhookUrl
    URL del webhook híbrido de n8n. Ej: https://n8n.qamilunastudio.com/webhook/hybrid-whatsapp

.PARAMETER Phone
    Número destino de la operadora (con código de país, sin espacios). Ej: 56950172974

.PARAMETER Instance
    Nombre de la instancia Evolution. Ej: qamiluna-test

.PARAMETER ActionsEnabled
    Activa ($true) o desactiva ($false) el envío de WhatsApp.

.PARAMETER AllowedPhones
    Lista separada por comas de números permitidos. Ej: 56950172974,56912345678

.PARAMETER Show
    Muestra la configuración actual sin modificar nada.

.EXAMPLE
    # Ver configuración actual
    .\tools\chatbots\Set-ChatbotWhatsAppOptions.ps1 -Show

.EXAMPLE
    # Configurar número destino operadora y habilitar envíos
    .\tools\chatbots\Set-ChatbotWhatsAppOptions.ps1 `
        -Phone 56950172974 `
        -ActionsEnabled $true `
        -AllowedPhones 56950172974

.EXAMPLE
    # Configuración completa
    .\tools\chatbots\Set-ChatbotWhatsAppOptions.ps1 `
        -WebhookUrl "https://n8n.qamilunastudio.com/webhook/hybrid-whatsapp" `
        -Phone 56950172974 `
        -Instance qamiluna-test `
        -ActionsEnabled $true `
        -AllowedPhones 56950172974
#>

param(
    [string] $Site = 'qamiluna',
    [string] $WebhookUrl,
    [string] $Phone,
    [string] $Instance,
    [nullable[bool]] $ActionsEnabled,
    [string] $AllowedPhones,
    [switch] $Show
)

$ErrorActionPreference = 'Stop'
$dotEnv = & "$PSScriptRoot/../http/Import-DotEnv.ps1"

function Resolve-Setting {
    param([string] $Name)
    $v = [Environment]::GetEnvironmentVariable($Name, 'Process')
    if (-not [string]::IsNullOrWhiteSpace($v)) { return $v }
    if ($dotEnv.ContainsKey($Name) -and -not [string]::IsNullOrWhiteSpace($dotEnv[$Name])) { return $dotEnv[$Name] }
    return [Environment]::GetEnvironmentVariable($Name, 'User')
}

$apiBase = Resolve-Setting 'QS_API_BASE'
$apiUser = Resolve-Setting 'QS_API_USER'
$apiPass = Resolve-Setting 'QS_API_PASS'

if ([string]::IsNullOrWhiteSpace($apiBase)) { $apiBase = 'https://qamilunastudio.com/wp-json/qs/v1' }

if ([string]::IsNullOrWhiteSpace($apiUser) -or [string]::IsNullOrWhiteSpace($apiPass)) {
    throw 'Faltan QS_API_USER o QS_API_PASS. Defínelas en .env o ejecuta tools/http/Set-QsApiEnv.ps1.'
}

$endpoint = '{0}/agents/whatsapp-options' -f $apiBase.TrimEnd('/')
$credentials = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('{0}:{1}' -f $apiUser, $apiPass))
$headers = @{
    'Authorization' = 'Basic ' + $credentials
    'Content-Type'  = 'application/json'
    'Accept'        = 'application/json'
}

# --- SHOW ---
if ($Show -or (-not $PSBoundParameters.ContainsKey('WebhookUrl') -and
               -not $PSBoundParameters.ContainsKey('Phone') -and
               -not $PSBoundParameters.ContainsKey('Instance') -and
               -not $PSBoundParameters.ContainsKey('ActionsEnabled') -and
               -not $PSBoundParameters.ContainsKey('AllowedPhones'))) {

    Write-Host "`n📋 Configuración actual de WhatsApp Gateway ($Site):" -ForegroundColor Cyan
    $resp = Invoke-RestMethod -Uri $endpoint -Method GET -Headers $headers
    $opts = $resp.options
    Write-Host "  webhook_url    : $($opts.webhook_url)"
    Write-Host "  phone (destino): $($opts.phone)"
    Write-Host "  instance       : $($opts.instance)"
    Write-Host "  actions_enabled: $($opts.actions_enabled)"
    Write-Host "  allowed_phones : $($opts.allowed_phones -join ', ')"
    return
}

# --- UPDATE ---
$body = @{}

if (-not [string]::IsNullOrWhiteSpace($WebhookUrl))  { $body['webhook_url']     = $WebhookUrl.Trim() }
if (-not [string]::IsNullOrWhiteSpace($Phone))        { $body['phone']           = $Phone.Trim() }
if (-not [string]::IsNullOrWhiteSpace($Instance))     { $body['instance']        = $Instance.Trim() }
if ($null -ne $ActionsEnabled)                        { $body['actions_enabled'] = $ActionsEnabled }
if (-not [string]::IsNullOrWhiteSpace($AllowedPhones)){ $body['allowed_phones']  = $AllowedPhones.Trim() }

if ($body.Count -eq 0) {
    Write-Host 'No se enviaron parámetros. Usa -Show para ver la configuración actual.' -ForegroundColor Yellow
    return
}

Write-Host "`n⚙️  Actualizando opciones de WhatsApp Gateway ($Site)..." -ForegroundColor Cyan

$resp = Invoke-RestMethod -Uri $endpoint -Method POST -Headers $headers -Body ($body | ConvertTo-Json -Compress)

if ($resp.success) {
    Write-Host "✅ Opciones actualizadas: $($resp.updated -join ', ')" -ForegroundColor Green
} else {
    Write-Host "❌ Error al actualizar opciones." -ForegroundColor Red
    $resp | ConvertTo-Json | Write-Host
}

# Mostrar estado final
Write-Host ''
$final = Invoke-RestMethod -Uri $endpoint -Method GET -Headers $headers
$opts = $final.options
Write-Host '📋 Estado actual:' -ForegroundColor Cyan
Write-Host "  webhook_url    : $($opts.webhook_url)"
Write-Host "  phone (destino): $($opts.phone)"
Write-Host "  instance       : $($opts.instance)"
Write-Host "  actions_enabled: $($opts.actions_enabled)"
Write-Host "  allowed_phones : $($opts.allowed_phones -join ', ')"

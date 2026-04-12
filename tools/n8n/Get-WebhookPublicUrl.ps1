#Requires -Version 5.1
<#
.SYNOPSIS
    Determina la URL pública correcta del webhook evolution-inbound según
    dónde corre n8n (Railway vs local+tunnel).

    Salida: escribe N8N_WEBHOOK_PUBLIC_URL en el entorno de proceso
            y muestra la URL a usar en Evolution.

    Variables que lee:
        N8N_BASE_URL            → si es URL pública (https://), la usa directamente
        N8N_WEBHOOK_PUBLIC_URL  → override manual (si usas tunnel propio)
#>
$ErrorActionPreference = 'Stop'

function Resolve-Env {
    param([string]$Name, [string]$Default = '')
    $v = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($v)) { return $Default }
    return $v.Trim()
}

$n8nBase    = (Resolve-Env 'N8N_BASE_URL').TrimEnd('/')
$overrideUrl = Resolve-Env 'N8N_WEBHOOK_PUBLIC_URL'

Write-Host "`n══════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Resolviendo URL pública del webhook  " -ForegroundColor Cyan
Write-Host "══════════════════════════════════════`n" -ForegroundColor Cyan

# 1. Override manual
if ($overrideUrl -ne '') {
    $webhookUrl = "$overrideUrl/webhook/evolution-inbound"
    Write-Host "Usando N8N_WEBHOOK_PUBLIC_URL (override manual)" -ForegroundColor Yellow
}
# 2. n8n en Railway / nube (URL pública)
elseif ($n8nBase -match '^https://') {
    $webhookUrl = "$n8nBase/webhook/evolution-inbound"
    Write-Host "n8n en nube (Railway): usando N8N_BASE_URL directamente" -ForegroundColor Green
}
# 3. n8n local — necesita tunnel
elseif ($n8nBase -match '^http://localhost' -or $n8nBase -match '^http://127') {
    Write-Host "n8n LOCAL detectado. Necesitas exponer el puerto 5678 a internet." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Opciones para obtener URL publica:" -ForegroundColor Cyan
    Write-Host "  A) ngrok (recomendado si lo tienes instalado):" -ForegroundColor White
    Write-Host "     ngrok http 5678" -ForegroundColor Gray
    Write-Host "     -> Copia la URL https://XXXX.ngrok.io"
    Write-Host ""
    Write-Host "  B) Serveo (sin instalacion, via SSH):" -ForegroundColor White
    Write-Host "     .\start-tunnel.ps1" -ForegroundColor Gray
    Write-Host "     -> Nota la URL https://XXXX.serveo.net"
    Write-Host ""
    Write-Host "  C) Cloudflare Tunnel (si tienes cloudflared):" -ForegroundColor White
    Write-Host "     cloudflared tunnel --url http://localhost:5678" -ForegroundColor Gray
    Write-Host ""

    $tunnelUrl = Read-Host "Pega aqui la URL del tunnel (ej: https://XXXX.ngrok.io) o Enter para omitir"
    if ($tunnelUrl -ne '') {
        $tunnelUrl = $tunnelUrl.TrimEnd('/')
        $webhookUrl = "$tunnelUrl/webhook/evolution-inbound"
        [Environment]::SetEnvironmentVariable('N8N_WEBHOOK_PUBLIC_URL', $tunnelUrl, 'Process')
        Write-Host "N8N_WEBHOOK_PUBLIC_URL guardado en sesion." -ForegroundColor Green
    } else {
        Write-Host "[AVISO] Sin URL de tunnel. Set-EvolutionWebhook.ps1 no podra continuar." -ForegroundColor Red
        return
    }
} else {
    Write-Host "[ERROR] N8N_BASE_URL no definido o formato no reconocido: '$n8nBase'" -ForegroundColor Red
    Write-Host "Define N8N_BASE_URL en tu .env.e2e" -ForegroundColor Gray
    return
}

[Environment]::SetEnvironmentVariable('N8N_WEBHOOK_PUBLIC_URL', ($webhookUrl -replace '/webhook/evolution-inbound$', ''), 'Process')

Write-Host ""
Write-Host "URL del webhook para Evolution:" -ForegroundColor Green
Write-Host "  $webhookUrl" -ForegroundColor White
Write-Host ""
Write-Host "Copia esta URL en Set-EvolutionWebhook.ps1 o corre:" -ForegroundColor Gray
Write-Host "  `$env:N8N_BASE_URL = '$($webhookUrl -replace '/webhook/evolution-inbound$', '')'" -ForegroundColor Gray
Write-Host "  .\Set-EvolutionWebhook.ps1" -ForegroundColor Gray
Write-Host ""

# Output parseable
"webhook_url=$webhookUrl"

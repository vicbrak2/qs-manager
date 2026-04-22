#Requires -Version 5.1
<#
.SYNOPSIS
    Crea una instancia nueva en Evolution API local y muestra el QR para conectar.
    Uso: .\New-EvolutionInstance.ps1 [-InstanceName "qamiluna-test"]

    Después de correr este script:
    1. Abre http://localhost:8080/manager
    2. Busca tu instancia → haz clic en "QR Code"
    3. Escanea con tu teléfono (WhatsApp → Dispositivos vinculados → Vincular dispositivo)
#>
param(
    [string]$InstanceName = 'qamiluna-test'
)

$ErrorActionPreference = 'Stop'

$baseUrl = if ($env:EVOLUTION_API_BASE_URL) { $env:EVOLUTION_API_BASE_URL.TrimEnd('/') } else { 'http://localhost:8080' }
$apiKey  = if ($env:EVOLUTION_API_KEY) { $env:EVOLUTION_API_KEY } else { $env:EVOLUTION_KEY }

if ([string]::IsNullOrWhiteSpace($apiKey)) {
    throw 'Define EVOLUTION_API_KEY o EVOLUTION_KEY antes de crear una instancia.'
}

$headers = @{
    apikey         = $apiKey
    'Content-Type' = 'application/json'
    Accept         = 'application/json'
}

Write-Host "`nCreando instancia '$InstanceName' en Evolution..." -ForegroundColor Cyan

# 1. Verificar si ya existe
try {
    $existing = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/instance/fetchInstances" `
        -Headers $headers -TimeoutSec 10
    $arr = if ($existing -is [System.Array]) { $existing } else { @($existing) }
    $found = $arr | Where-Object { [string]$_.name -eq $InstanceName } | Select-Object -First 1
    if ($found) {
        $status = [string]$found.connectionStatus
        Write-Host "[!] La instancia '$InstanceName' ya existe (status=$status)" -ForegroundColor Yellow
        if ($status -eq 'open') {
            Write-Host "    Ya esta conectada. No necesitas crear una nueva." -ForegroundColor Green
            return
        }
        Write-Host "    No esta conectada. Intentando reconectar..." -ForegroundColor Yellow
        # Intentar conectar la instancia existente
        try {
            $conn = Invoke-RestMethod -Method Get `
                -Uri "$baseUrl/instance/connect/$InstanceName" `
                -Headers $headers -TimeoutSec 15
            Write-Host ""
            if ($conn.qrcode) {
                Write-Host "QR generado. Opciones para escanearlo:" -ForegroundColor Green
                Write-Host "  1. Abre http://localhost:8080/manager → instancia → QR Code" -ForegroundColor White
                Write-Host "  2. O usa este QR en base64 con un visor online" -ForegroundColor White
            } else {
                $conn | ConvertTo-Json | Write-Host
            }
        } catch {
            Write-Warning "No se pudo reconectar: $($_.Exception.Message)"
        }
        return
    }
} catch {
    Write-Warning "No se pudo verificar instancias existentes: $($_.Exception.Message)"
}

# 2. Crear instancia nueva
$body = @{
    instanceName  = $InstanceName
    qrcode        = $true
    integration   = 'WHATSAPP-BAILEYS'
    number        = ''
    token         = ''
} | ConvertTo-Json

try {
    $resp = Invoke-RestMethod -Method Post `
        -Uri "$baseUrl/instance/create" `
        -Headers $headers `
        -Body $body `
        -TimeoutSec 20

    Write-Host "Instancia creada OK:" -ForegroundColor Green
    Write-Host "  Nombre : $($resp.instance.instanceName)" -ForegroundColor White
    Write-Host "  Status : $($resp.instance.status)" -ForegroundColor White
    Write-Host ""
    Write-Host "Siguiente paso — conectar WhatsApp:" -ForegroundColor Cyan
    Write-Host "  1. Abre http://localhost:8080/manager en tu navegador" -ForegroundColor White
    Write-Host "  2. Busca la instancia '$InstanceName'" -ForegroundColor White
    Write-Host "  3. Haz clic en el icono de QR Code" -ForegroundColor White
    Write-Host "  4. En tu telefono: WhatsApp → '...' → Dispositivos vinculados → Vincular dispositivo" -ForegroundColor White
    Write-Host "  5. Escanea el QR" -ForegroundColor White
    Write-Host ""
    Write-Host "Una vez conectado, corre Check-EvolutionReadiness.ps1 para confirmar." -ForegroundColor Yellow

} catch {
    $errMsg = $_.Exception.Message
    Write-Host "[FAIL] $errMsg" -ForegroundColor Red

    # Diagnóstico común
    if ($errMsg -match '401|Unauthorized') {
        Write-Host "-> API key incorrecta. Verifica AUTHENTICATION_API_KEY en tu .env de Evolution." -ForegroundColor Gray
    } elseif ($errMsg -match 'refused|connect') {
        Write-Host "-> Evolution no responde en $baseUrl. Verifica que el contenedor este corriendo:" -ForegroundColor Gray
        Write-Host "   docker ps | grep evolution" -ForegroundColor Gray
    }
}

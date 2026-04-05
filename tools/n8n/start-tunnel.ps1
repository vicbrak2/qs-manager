param(
    [string]$RemoteHost = 'serveo.net',
    [int]$LocalPort = 5678,
    [int]$RemotePort = 80
)

Write-Host "Iniciando tunel SSH -> localhost:$LocalPort..." -ForegroundColor Green
Write-Host "Deja esta ventana abierta mientras trabajas con n8n." -ForegroundColor Yellow
Write-Host ""

while ($true) {
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Conectando a $RemoteHost..." -ForegroundColor Cyan
    ssh -o StrictHostKeyChecking=no `
        -o ServerAliveInterval=30 `
        -o ServerAliveCountMax=3 `
        -R "${RemotePort}:localhost:${LocalPort}" `
        $RemoteHost
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Tunel caido. Reconectando en 5s..." -ForegroundColor Red
    Start-Sleep -Seconds 5
}

#Requires -Version 5.1
<#
.SYNOPSIS
    Carga variables de .env.e2e al entorno de la sesión PowerShell actual.
    Uso: . .\Load-E2EEnv.ps1   (con el punto al inicio para importar al scope actual)
    O bien: .\Load-E2EEnv.ps1  (carga y muestra resumen)
#>
$envFile = Join-Path $PSScriptRoot '.env.e2e'

if (-not (Test-Path $envFile)) {
    Write-Host "[AVISO] No se encontro .env.e2e en $PSScriptRoot" -ForegroundColor Yellow
    Write-Host "Copia e2e-env-example.txt como .env.e2e y completa tus valores." -ForegroundColor Gray
    return
}

$loaded = 0
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    # Ignorar comentarios y líneas vacías
    if ($line -eq '' -or $line.StartsWith('#')) { return }

    $parts = $line -split '=', 2
    if ($parts.Count -eq 2) {
        $name  = $parts[0].Trim()
        $value = $parts[1].Trim().Trim("'").Trim('"')
        [Environment]::SetEnvironmentVariable($name, $value, 'Process')
        $loaded++
    }
}

Write-Host "`nVariables cargadas desde .env.e2e ($loaded variables):" -ForegroundColor Green

$vars = @(
    'EVOLUTION_API_BASE_URL'
    'EVOLUTION_INSTANCE_NAME'
    'EVOLUTION_API_KEY'
    'N8N_BASE_URL'
    'N8N_API_KEY'
    'WORDPRESS_URL'
)

foreach ($v in $vars) {
    $val = [Environment]::GetEnvironmentVariable($v)
    if ([string]::IsNullOrWhiteSpace($val)) {
        Write-Host "  [!] $v = (no definida)" -ForegroundColor Red
    } elseif ($v -like '*KEY*' -or $v -like '*PASSWORD*' -or $v -like '*SECRET*') {
        $suffix = $val.Substring([Math]::Max(0, $val.Length - 4))
        Write-Host "  [ok] $v = ****$suffix" -ForegroundColor Cyan
    } else {
        Write-Host "  [ok] $v = $val" -ForegroundColor Cyan
    }
}
Write-Host ""

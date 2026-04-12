$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$dotenvPath = Join-Path $repoRoot '.env'
function Clear-UnresolvedPlaceholder {
    param([string] $Name)

    $value = [Environment]::GetEnvironmentVariable($Name)

    if ($value -match '^\$\{[A-Z0-9_]+\}$') {
        [Environment]::SetEnvironmentVariable($Name, '')
    }
}

$mcpKeys = @(
    'N8N_API_URL',
    'N8N_BASE_URL',
    'N8N_API_KEY',
    'N8N_CHATBOT_TOKEN',
    'N8N_QAMILUNA_INSTANCE'
)

foreach ($k in $mcpKeys) {
    Clear-UnresolvedPlaceholder -Name $k
}

if (Test-Path $dotenvPath) {

    foreach ($line in Get-Content -Path $dotenvPath) {
        if ($line -match '^\s*#' -or $line -notmatch '^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$') {
            continue
        }

        $key = $matches[1].Trim()
        $value = $matches[2].Trim().Trim('"')

        if ($mcpKeys -contains $key -and [string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($key))) {
            [Environment]::SetEnvironmentVariable($key, $value)
        }
    }
}

if ([string]::IsNullOrWhiteSpace($env:N8N_BASE_URL)) {
    if (-not [string]::IsNullOrWhiteSpace($env:N8N_API_URL)) {
        $env:N8N_BASE_URL = $env:N8N_API_URL
    } else {
        $env:N8N_BASE_URL = 'http://localhost:5678'
    }
}

if ([string]::IsNullOrWhiteSpace($env:N8N_API_KEY)) {
    if (-not [string]::IsNullOrWhiteSpace($env:N8N_CHATBOT_TOKEN)) {
        $env:N8N_API_KEY = $env:N8N_CHATBOT_TOKEN
    } elseif (-not [string]::IsNullOrWhiteSpace($env:N8N_QAMILUNA_INSTANCE)) {
        $env:N8N_API_KEY = $env:N8N_QAMILUNA_INSTANCE
    }
}

$tsxPath = Join-Path $PSScriptRoot 'node_modules\.bin\tsx.cmd'

if (-not (Test-Path $tsxPath)) {
    throw "No se encontro tsx en $tsxPath. Ejecuta: npm --prefix tools/mcp-n8n install"
}

& $tsxPath (Join-Path $PSScriptRoot 'src\index.ts')
exit $LASTEXITCODE

param(
    [Parameter(Mandatory = $true)]
    [string] $Path,
    [ValidateSet('GET', 'POST', 'PUT', 'PATCH', 'DELETE')]
    [string] $Method = 'GET'
)

$dotEnv = & "$PSScriptRoot/Import-DotEnv.ps1"

function Resolve-Setting {
    param(
        [string] $Name
    )

    $processValue = [Environment]::GetEnvironmentVariable($Name, 'Process')
    if (-not [string]::IsNullOrWhiteSpace($processValue)) {
        return $processValue
    }

    if ($dotEnv.ContainsKey($Name) -and -not [string]::IsNullOrWhiteSpace($dotEnv[$Name])) {
        return $dotEnv[$Name]
    }

    return [Environment]::GetEnvironmentVariable($Name, 'User')
}

$apiBase = Resolve-Setting 'QS_API_BASE'
$apiUser = Resolve-Setting 'QS_API_USER'
$apiPass = Resolve-Setting 'QS_API_PASS'

if ([string]::IsNullOrWhiteSpace($apiBase) -or [string]::IsNullOrWhiteSpace($apiUser) -or [string]::IsNullOrWhiteSpace($apiPass)) {
    throw 'Faltan QS_API_BASE, QS_API_USER o QS_API_PASS. Definelas en .env, en la sesión actual o ejecuta tools/http/Set-QsApiEnv.ps1.'
}

$uri = if ($Path.StartsWith('http')) {
    $Path
} else {
    '{0}/{1}' -f $apiBase.TrimEnd('/'), $Path.TrimStart('/')
}

$arguments = @(
    '-sS',
    '-i',
    '-X', $Method,
    '-u', ('{0}:{1}' -f $apiUser, $apiPass),
    $uri
)

& curl.exe @arguments

if ($LASTEXITCODE -ne 0) {
    throw ('curl.exe terminó con código ' + $LASTEXITCODE)
}

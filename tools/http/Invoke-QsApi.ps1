param(
    [Parameter(Mandatory = $true)]
    [string] $Path,
    [ValidateSet('GET', 'POST', 'PUT', 'PATCH', 'DELETE')]
    [string] $Method = 'GET'
)

$apiBase = [Environment]::GetEnvironmentVariable('QS_API_BASE', 'Process')
if ([string]::IsNullOrWhiteSpace($apiBase)) {
    $apiBase = [Environment]::GetEnvironmentVariable('QS_API_BASE', 'User')
}

$apiUser = [Environment]::GetEnvironmentVariable('QS_API_USER', 'Process')
if ([string]::IsNullOrWhiteSpace($apiUser)) {
    $apiUser = [Environment]::GetEnvironmentVariable('QS_API_USER', 'User')
}

$apiPass = [Environment]::GetEnvironmentVariable('QS_API_PASS', 'Process')
if ([string]::IsNullOrWhiteSpace($apiPass)) {
    $apiPass = [Environment]::GetEnvironmentVariable('QS_API_PASS', 'User')
}

if ([string]::IsNullOrWhiteSpace($apiBase) -or [string]::IsNullOrWhiteSpace($apiUser) -or [string]::IsNullOrWhiteSpace($apiPass)) {
    throw 'Faltan variables QS_API_BASE, QS_API_USER o QS_API_PASS. Ejecuta tools/http/Set-QsApiEnv.ps1 primero.'
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

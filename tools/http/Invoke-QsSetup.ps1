param(
    [string] $SiteName,
    [string] $SiteDescription,
    [string] $PermalinkStructure = '/%postname%/',
    [string] $MenuName,
    [string] $MenuLocation = 'primary',
    [string] $FrontPageSlug = 'home',
    [switch] $Force
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

$payload = [ordered]@{}

if (-not [string]::IsNullOrWhiteSpace($SiteName)) {
    $payload.site_name = $SiteName
}

if (-not [string]::IsNullOrWhiteSpace($SiteDescription)) {
    $payload.site_description = $SiteDescription
}

if (-not [string]::IsNullOrWhiteSpace($PermalinkStructure)) {
    $payload.permalink_structure = $PermalinkStructure
}

if (-not [string]::IsNullOrWhiteSpace($MenuName)) {
    $payload.menu_name = $MenuName
}

if (-not [string]::IsNullOrWhiteSpace($MenuLocation)) {
    $payload.menu_location = $MenuLocation
}

if (-not [string]::IsNullOrWhiteSpace($FrontPageSlug)) {
    $payload.front_page_slug = $FrontPageSlug
}

if ($Force.IsPresent) {
    $payload.force = $true
}

$body = if ($payload.Count -gt 0) {
    $payload | ConvertTo-Json -Depth 4
} else {
    '{}'
}

$uri = '{0}/{1}' -f $apiBase.TrimEnd('/'), 'setup'
$arguments = @(
    '-sS',
    '-i',
    '-X', 'POST',
    '-u', ('{0}:{1}' -f $apiUser, $apiPass),
    '-H', 'Content-Type: application/json',
    '-H', 'Accept: application/json',
    '--data-binary', $body,
    $uri
)

& curl.exe @arguments

if ($LASTEXITCODE -ne 0) {
    throw ('curl.exe terminó con código ' + $LASTEXITCODE)
}

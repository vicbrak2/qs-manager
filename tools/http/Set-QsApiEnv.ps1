param(
    [string] $User,
    [string] $Password,
    [string] $Base
)

$dotEnv = & "$PSScriptRoot/Import-DotEnv.ps1"

if ([string]::IsNullOrWhiteSpace($User)) {
    $User = if ($dotEnv.ContainsKey('QS_API_USER')) { $dotEnv['QS_API_USER'] } else { 'admin_qs' }
}

if ([string]::IsNullOrWhiteSpace($Password) -and $dotEnv.ContainsKey('QS_API_PASS')) {
    $Password = $dotEnv['QS_API_PASS']
}

if ([string]::IsNullOrWhiteSpace($Base)) {
    $Base = if ($dotEnv.ContainsKey('QS_API_BASE')) { $dotEnv['QS_API_BASE'] } else { 'https://qamilunastudio.com/wp-json/qs/v1' }
}

if ([string]::IsNullOrWhiteSpace($Password)) {
    throw 'Falta QS_API_PASS. Pásala por parámetro o defínela en .env.'
}

[Environment]::SetEnvironmentVariable('QS_API_USER', $User, 'User')
[Environment]::SetEnvironmentVariable('QS_API_PASS', $Password, 'User')
[Environment]::SetEnvironmentVariable('QS_API_BASE', $Base.TrimEnd('/'), 'User')

Write-Output 'Variables QS_API_* guardadas a nivel usuario.'
Write-Output 'Si ya están en .env, este paso es opcional.'
Write-Output 'Cierra y vuelve a abrir la terminal antes de usarlas.'

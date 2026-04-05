param(
    [string] $User = 'admin_qs',
    [Parameter(Mandatory = $true)]
    [string] $Password,
    [string] $Base = 'https://qamilunastudio.com/wp-json/qs/v1'
)

[Environment]::SetEnvironmentVariable('QS_API_USER', $User, 'User')
[Environment]::SetEnvironmentVariable('QS_API_PASS', $Password, 'User')
[Environment]::SetEnvironmentVariable('QS_API_BASE', $Base.TrimEnd('/'), 'User')

Write-Output 'Variables guardadas a nivel usuario.'
Write-Output 'Cierra y vuelve a abrir la terminal antes de usarlas.'

#Requires -Version 5.1
param(
    [string] $Site = 'qamiluna',
    [string] $ConfigPath = '',
    [string] $EnvFile = '',
    [string] $RootEnvFile = '',
    [switch] $SkipPostgres
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

& (Join-Path $PSScriptRoot 'Test-ChatbotDeployment.ps1') -Site $Site -ConfigPath $ConfigPath -EnvFile $EnvFile -RootEnvFile $RootEnvFile
& (Join-Path $PSScriptRoot 'Export-N8nWorkflows.ps1') -Site $Site -ConfigPath $ConfigPath -EnvFile $EnvFile -RootEnvFile $RootEnvFile -ActiveOnly

if (-not $SkipPostgres) {
    & (Join-Path $PSScriptRoot 'Backup-RailwayPostgres.ps1') -Site $Site -ConfigPath $ConfigPath -EnvFile $EnvFile -RootEnvFile $RootEnvFile
}

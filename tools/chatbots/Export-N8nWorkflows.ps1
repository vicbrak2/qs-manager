#Requires -Version 5.1
param(
    [string] $Site = 'qamiluna',
    [string] $ConfigPath = '',
    [string] $EnvFile = '',
    [string] $RootEnvFile = '',
    [switch] $ActiveOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'ChatbotOps.psm1') -Force

$ctx = New-ChatbotOpsContext -Site $Site -ConfigPath $ConfigPath -EnvFile $EnvFile -RootEnvFile $RootEnvFile
if ($ctx.N8nBaseUrl -eq '' -or $ctx.N8nApiKey -eq '') {
    throw 'Faltan N8N_BASE_URL o N8N_API_KEY para exportar workflows.'
}

$headers = @{ 'X-N8N-API-KEY' = $ctx.N8nApiKey; Accept = 'application/json' }
$target = New-ChatbotBackupPath -Context $ctx -Kind 'n8n-workflows'
$list = Invoke-RestMethod -Method Get -Uri "$($ctx.N8nBaseUrl)/api/v1/workflows?limit=250" -Headers $headers -TimeoutSec 30
$workflows = @($list.data)
if ($ActiveOnly) {
    $workflows = $workflows | Where-Object { $_.active -eq $true }
}

$index = [System.Collections.Generic.List[object]]::new()
foreach ($workflow in $workflows) {
    $full = Invoke-RestMethod -Method Get -Uri "$($ctx.N8nBaseUrl)/api/v1/workflows/$($workflow.id)" -Headers $headers -TimeoutSec 30
    $safeName = ($workflow.name -replace '[^a-zA-Z0-9._-]+', '-').Trim('-')
    $file = Join-Path $target "$safeName.$($workflow.id).json"
    $full | ConvertTo-Json -Depth 100 | Set-Content -LiteralPath $file -Encoding UTF8
    $index.Add([pscustomobject]@{
        id = $workflow.id
        name = $workflow.name
        active = $workflow.active
        file = (Split-Path -Leaf $file)
    })
}

$manifest = [pscustomobject]@{
    site = $ctx.Site.id
    n8nBaseUrl = $ctx.N8nBaseUrl
    exportedAt = (Get-Date).ToString('o')
    activeOnly = [bool]$ActiveOnly
    count = @($index).Count
    workflows = @($index)
}

$manifest | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $target 'manifest.json') -Encoding UTF8
Write-Host "Exportados $(@($index).Count) workflow(s) a $target" -ForegroundColor Green

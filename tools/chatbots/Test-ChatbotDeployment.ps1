#Requires -Version 5.1
param(
    [string] $Site = 'qamiluna',
    [string] $ConfigPath = '',
    [string] $EnvFile = '',
    [string] $RootEnvFile = '',
    [string] $AlertWebhookUrl = '',
    [int] $EvolutionRetries = 3,
    [int] $EvolutionRetryDelaySeconds = 15,
    [switch] $Json
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'ChatbotOps.psm1') -Force

$ctx = New-ChatbotOpsContext -Site $Site -ConfigPath $ConfigPath -EnvFile $EnvFile -RootEnvFile $RootEnvFile
$checks = [System.Collections.Generic.List[object]]::new()

function Add-Check {
    param([string] $Name, [bool] $Ok, [string] $Detail = '')
    $checks.Add([pscustomobject]@{ name = $Name; ok = $Ok; detail = $Detail })
}

try {
    Add-Check 'config.site' $true "$($ctx.Site.id) / $($ctx.Site.label)"
    Add-Check 'config.n8n_base_url' ($ctx.N8nBaseUrl -ne '') $ctx.N8nBaseUrl
    Add-Check 'config.n8n_api_key' ($ctx.N8nApiKey -ne '') (Get-MaskedSuffix $ctx.N8nApiKey)
    Add-Check 'config.evolution_base_url' ($ctx.EvolutionBaseUrl -ne '') $ctx.EvolutionBaseUrl
    Add-Check 'config.evolution_api_key' ($ctx.EvolutionApiKey -ne '') (Get-MaskedSuffix $ctx.EvolutionApiKey)
    Add-Check 'config.evolution_instance' ($ctx.EvolutionInstanceName -ne '') $ctx.EvolutionInstanceName

    if ($ctx.N8nBaseUrl -ne '') {
        $health = Invoke-WebRequest -Method Get -Uri "$($ctx.N8nBaseUrl)/healthz" -TimeoutSec 20 -UseBasicParsing
        Add-Check 'n8n.healthz' ($health.StatusCode -eq 200) "HTTP $($health.StatusCode)"
    }

    if ($ctx.N8nBaseUrl -ne '' -and $ctx.N8nApiKey -ne '') {
        $headers = @{ 'X-N8N-API-KEY' = $ctx.N8nApiKey; Accept = 'application/json' }
        $workflowResponse = Invoke-RestMethod -Method Get -Uri "$($ctx.N8nBaseUrl)/api/v1/workflows?limit=100" -Headers $headers -TimeoutSec 30
        $workflows = @($workflowResponse.data)
        foreach ($workflowName in @($ctx.Site.n8nRequiredWorkflows)) {
            $workflow = $workflows | Where-Object { $_.name -eq $workflowName } | Select-Object -First 1
            Add-Check "n8n.workflow.$workflowName" ($null -ne $workflow -and $workflow.active -eq $true) $(if ($workflow) { "active=$($workflow.active)" } else { 'missing' })
        }
    }

    if ($ctx.EvolutionBaseUrl -ne '' -and $ctx.EvolutionApiKey -ne '' -and $ctx.EvolutionInstanceName -ne '') {
        $headers = @{ apikey = $ctx.EvolutionApiKey; Accept = 'application/json' }
        try {
            $expected = [string]$ctx.Site.evolutionExpectedState
            $current = ''
            for ($attempt = 1; $attempt -le [Math]::Max(1, $EvolutionRetries); $attempt++) {
                try {
                    $state = Invoke-RestMethod -Method Get -Uri "$($ctx.EvolutionBaseUrl)/instance/connectionState/$($ctx.EvolutionInstanceName)" -Headers $headers -TimeoutSec 30
                    $current = [string]$state.instance.state
                } catch {
                    $instances = Invoke-RestMethod -Method Get -Uri "$($ctx.EvolutionBaseUrl)/instance/fetchInstances" -Headers $headers -TimeoutSec 30
                    $instance = @($instances) | Where-Object { [string]$_.name -eq $ctx.EvolutionInstanceName } | Select-Object -First 1
                    if ($instance) {
                        $current = [string]$instance.connectionStatus
                    } else {
                        throw
                    }
                }
                if ($current -eq $expected -or $attempt -eq $EvolutionRetries) {
                    break
                }
                Start-Sleep -Seconds $EvolutionRetryDelaySeconds
            }
            Add-Check 'evolution.connection_state' ($current -eq $expected) "state=$current expected=$expected"
        } catch {
            Add-Check 'evolution.connection_state' $false $_.Exception.Message
        }

        try {
            $webhook = Invoke-RestMethod -Method Get -Uri "$($ctx.EvolutionBaseUrl)/webhook/find/$($ctx.EvolutionInstanceName)" -Headers $headers -TimeoutSec 60
            Add-Check 'evolution.webhook.enabled' ($webhook.enabled -eq $true) "url=$($webhook.url)"
        } catch {
            Add-Check 'evolution.webhook.enabled' $false $_.Exception.Message
        }
    }

    if ($ctx.Site.wordpressUrl) {
        try {
            $wp = Invoke-WebRequest -Method Get -Uri ([string]$ctx.Site.wordpressUrl).TrimEnd('/') -TimeoutSec 20 -UseBasicParsing
            Add-Check 'wordpress.home' ($wp.StatusCode -lt 400) "HTTP $($wp.StatusCode)"
        } catch {
            Add-Check 'wordpress.home' $false $_.Exception.Message
        }
    }
} catch {
    Add-Check 'healthcheck.exception' $false $_.Exception.Message
}

$allOk = -not (@($checks) | Where-Object { -not $_.ok } | Select-Object -First 1)
$result = [pscustomobject]@{
    site = $ctx.Site.id
    ok = $allOk
    checkedAt = (Get-Date).ToString('o')
    checks = @($checks)
}

if ($AlertWebhookUrl -ne '' -and -not $allOk) {
    $payload = $result | ConvertTo-Json -Depth 8
    Invoke-RestMethod -Method Post -Uri $AlertWebhookUrl -ContentType 'application/json' -Body $payload -TimeoutSec 20 | Out-Null
}

if ($Json) {
    $result | ConvertTo-Json -Depth 8
} else {
    Write-Host "`nChatbot deployment health: $($ctx.Site.label)" -ForegroundColor Cyan
    foreach ($check in $checks) {
        $color = if ($check.ok) { 'Green' } else { 'Red' }
        $status = if ($check.ok) { '[OK]' } else { '[FAIL]' }
        Write-Host "$status $($check.name) $($check.detail)" -ForegroundColor $color
    }
    Write-Host ""
}

if (-not $allOk) {
    exit 1
}

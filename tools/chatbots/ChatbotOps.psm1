Set-StrictMode -Version Latest

function Get-ChatbotRepoRoot {
    $root = Resolve-Path (Join-Path (Join-Path $PSScriptRoot '..') '..')
    return $root.Path
}

function Import-ChatbotDotEnv {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path
    )

    $values = @{}
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return $values
    }

    Get-Content -LiteralPath $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '' -or $line.StartsWith('#') -or $line -notmatch '=') {
            return
        }

        $parts = $line -split '=', 2
        $name = $parts[0].Trim()
        $value = $parts[1].Trim().Trim("'").Trim('"')
        if ($name -ne '') {
            $values[$name] = $value
        }
    }

    return $values
}

function Get-ChatbotConfig {
    param(
        [string] $Site = 'qamiluna',
        [string] $ConfigPath = ''
    )

    $repoRoot = Get-ChatbotRepoRoot
    if ($ConfigPath -eq '') {
        $ConfigPath = Join-Path (Join-Path (Join-Path $repoRoot 'config') 'chatbots') 'sites.json'
    }

    if (-not (Test-Path -LiteralPath $ConfigPath -PathType Leaf)) {
        throw "No existe config de chatbots: $ConfigPath"
    }

    $config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
    $siteConfig = @($config.sites) | Where-Object { $_.id -eq $Site } | Select-Object -First 1

    if (-not $siteConfig) {
        $knownSites = (@($config.sites) | ForEach-Object { $_.id }) -join ', '
        throw "No existe el sitio '$Site'. Sitios disponibles: $knownSites"
    }

    return $siteConfig
}

function Resolve-ChatbotEnv {
    param(
        [string] $Name,
        [hashtable] $PrimaryEnv,
        [hashtable] $FallbackEnv,
        [string] $Default = ''
    )

    $processValue = [Environment]::GetEnvironmentVariable($Name)
    if (-not [string]::IsNullOrWhiteSpace($processValue)) {
        return $processValue.Trim()
    }

    if ($PrimaryEnv.ContainsKey($Name) -and -not [string]::IsNullOrWhiteSpace($PrimaryEnv[$Name])) {
        return [string]$PrimaryEnv[$Name]
    }

    if ($FallbackEnv.ContainsKey($Name) -and -not [string]::IsNullOrWhiteSpace($FallbackEnv[$Name])) {
        return [string]$FallbackEnv[$Name]
    }

    return $Default
}

function Resolve-ChatbotSecret {
    param(
        [string] $Name,
        [string] $FallbackName = '',
        [hashtable] $PrimaryEnv,
        [hashtable] $FallbackEnv
    )

    $value = Resolve-ChatbotEnv -Name $Name -PrimaryEnv $PrimaryEnv -FallbackEnv $FallbackEnv
    if ($value -ne '') {
        return $value
    }

    if ($FallbackName -ne '') {
        return Resolve-ChatbotEnv -Name $FallbackName -PrimaryEnv $PrimaryEnv -FallbackEnv $FallbackEnv
    }

    return ''
}

function New-ChatbotOpsContext {
    param(
        [string] $Site = 'qamiluna',
        [string] $ConfigPath = '',
        [string] $EnvFile = '',
        [string] $RootEnvFile = ''
    )

    $repoRoot = Get-ChatbotRepoRoot
    if ($EnvFile -eq '') {
        $EnvFile = Join-Path (Join-Path (Join-Path $repoRoot 'tools') 'n8n') '.env.e2e'
    }
    if ($RootEnvFile -eq '') {
        $RootEnvFile = Join-Path $repoRoot '.env'
    }

    $siteConfig = Get-ChatbotConfig -Site $Site -ConfigPath $ConfigPath
    $primaryEnv = Import-ChatbotDotEnv -Path $EnvFile
    $fallbackEnv = Import-ChatbotDotEnv -Path $RootEnvFile

    $n8nBase = Resolve-ChatbotEnv -Name $siteConfig.n8nBaseUrlEnv -PrimaryEnv $primaryEnv -FallbackEnv $fallbackEnv
    $n8nKey = Resolve-ChatbotSecret -Name $siteConfig.n8nApiKeyEnv -FallbackName $siteConfig.n8nApiKeyFallbackEnv -PrimaryEnv $primaryEnv -FallbackEnv $fallbackEnv
    $evolutionBase = Resolve-ChatbotEnv -Name $siteConfig.evolutionBaseUrlEnv -PrimaryEnv $primaryEnv -FallbackEnv $fallbackEnv
    $evolutionKey = Resolve-ChatbotSecret -Name $siteConfig.evolutionApiKeyEnv -FallbackName $siteConfig.evolutionApiKeyFallbackEnv -PrimaryEnv $primaryEnv -FallbackEnv $fallbackEnv
    $evolutionInstance = Resolve-ChatbotEnv -Name $siteConfig.evolutionInstanceNameEnv -PrimaryEnv $primaryEnv -FallbackEnv $fallbackEnv
    $railwayApiToken = Resolve-ChatbotEnv -Name 'RAILWAY_API_TOKEN' -PrimaryEnv $primaryEnv -FallbackEnv $fallbackEnv

    return [pscustomobject]@{
        RepoRoot = $repoRoot
        Site = $siteConfig
        EnvFile = $EnvFile
        RootEnvFile = $RootEnvFile
        N8nBaseUrl = $n8nBase.TrimEnd('/')
        N8nApiKey = $n8nKey
        EvolutionBaseUrl = $evolutionBase.TrimEnd('/')
        EvolutionApiKey = $evolutionKey
        EvolutionInstanceName = $evolutionInstance
        RailwayApiToken = $railwayApiToken
    }
}

function New-ChatbotBackupPath {
    param(
        [Parameter(Mandatory = $true)]
        [object] $Context,
        [Parameter(Mandatory = $true)]
        [string] $Kind
    )

    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $path = Join-Path (Join-Path (Join-Path (Join-Path (Join-Path $Context.RepoRoot 'var') 'backups') 'chatbots') $Context.Site.id) (Join-Path $Kind $timestamp)
    New-Item -ItemType Directory -Force -Path $path | Out-Null
    return $path
}

function Get-MaskedSuffix {
    param([string] $Value)
    if ([string]::IsNullOrWhiteSpace($Value)) {
        return '(missing)'
    }
    return '****' + $Value.Substring([Math]::Max(0, $Value.Length - 4))
}

Export-ModuleMember -Function `
    Get-ChatbotRepoRoot, `
    Import-ChatbotDotEnv, `
    Get-ChatbotConfig, `
    Resolve-ChatbotEnv, `
    Resolve-ChatbotSecret, `
    New-ChatbotOpsContext, `
    New-ChatbotBackupPath, `
    Get-MaskedSuffix

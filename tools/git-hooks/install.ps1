Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$sourceDir = Join-Path $repoRoot '.githooks'
$targetDir = Join-Path $repoRoot '.git\hooks'
$hooks = @('pre-commit')

if (-not (Test-Path -LiteralPath $sourceDir -PathType Container) -or -not (Test-Path -LiteralPath $targetDir -PathType Container)) {
    Write-Host 'Skipping git hook installation.'
    exit 0
}

foreach ($hook in $hooks) {
    $source = Join-Path $sourceDir $hook
    $target = Join-Path $targetDir $hook

    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        Write-Error "Missing hook source: $source"
    }

    Copy-Item -LiteralPath $source -Destination $target -Force
    Write-Host "Installed git hook: $hook"
}

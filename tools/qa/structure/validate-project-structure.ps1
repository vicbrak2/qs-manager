Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$configPath = Join-Path $repoRoot 'config\quality\project-structure.json'

if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
    Write-Error 'Missing structure config: config/quality/project-structure.json'
}

$config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
$errors = [System.Collections.Generic.List[string]]::new()

function Get-RelativeRepoPath {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RepoRoot,

        [Parameter(Mandatory = $true)]
        [string] $AbsolutePath
    )

    $root = [System.Uri]::new(((Resolve-Path -LiteralPath $RepoRoot).Path.TrimEnd('\') + '\'))
    $path = [System.Uri]::new($AbsolutePath)

    return [System.Uri]::UnescapeDataString($root.MakeRelativeUri($path).ToString()).Replace('\', '/')
}

function Test-GitIgnored {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RepoRoot,

        [Parameter(Mandatory = $true)]
        [string] $RelativePath
    )

    & git -C $RepoRoot check-ignore -q --no-index -- $RelativePath 2>$null
    return $LASTEXITCODE -eq 0
}

$allowedRootEntries = @{}
foreach ($entry in $config.allowed_root_entries) {
    $allowedRootEntries[$entry] = $true
}

Get-ChildItem -LiteralPath $repoRoot -Force | ForEach-Object {
    $name = $_.Name

    if (-not $allowedRootEntries.ContainsKey($name) -and -not (Test-GitIgnored -RepoRoot $repoRoot -RelativePath $name)) {
        $errors.Add("Raiz del repo: `"$name`" no esta permitido. Muevelo a la carpeta correcta o agregalo a la politica de estructura.")
    }
}

foreach ($rule in $config.restricted_paths) {
    $targetPath = Join-Path $repoRoot $rule.relative_path

    if (-not (Test-Path -LiteralPath $targetPath -PathType Container)) {
        continue
    }

    $allowedExtensions = @{}
    foreach ($extension in $rule.allowed_extensions) {
        $allowedExtensions[$extension.ToLowerInvariant()] = $true
    }

    $allowedFilenames = @{}
    foreach ($filename in $rule.allowed_filenames) {
        $allowedFilenames[$filename] = $true
    }

    Get-ChildItem -LiteralPath $targetPath -Recurse -Force -File | ForEach-Object {
        $relativePath = Get-RelativeRepoPath -RepoRoot $repoRoot -AbsolutePath $_.FullName
        $filename = $_.Name
        $extension = $_.Extension.TrimStart('.').ToLowerInvariant()

        if (
            -not (Test-GitIgnored -RepoRoot $repoRoot -RelativePath $relativePath) -and
            -not $allowedFilenames.ContainsKey($filename) -and
            -not ($extension -and $allowedExtensions.ContainsKey($extension))
        ) {
            $errors.Add("`"$($rule.relative_path)`" contiene `"$relativePath`", pero ahi deben vivir $($rule.description).")
        }
    }
}

if ($errors.Count -gt 0) {
    Write-Host 'Project structure validation failed.' -ForegroundColor Red
    Write-Host ''

    foreach ($validationError in $errors) {
        Write-Host "- $validationError"
    }

    Write-Host ''
    Write-Host 'Sugerencias:'
    Write-Host '- scripts y utilidades ejecutables -> tools/'
    Write-Host '- compose, workflows y definiciones de despliegue -> infrastructure/'
    Write-Host '- dumps, snapshots y archivos temporales locales -> var/tmp/'
    exit 1
}

Write-Host 'Project structure validation passed.' -ForegroundColor Green

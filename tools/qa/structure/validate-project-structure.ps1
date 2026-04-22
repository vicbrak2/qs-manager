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
    if ($null -ne $rule.allowed_extensions) {
        foreach ($extension in $rule.allowed_extensions) {
            $allowedExtensions[$extension.ToLowerInvariant()] = $true
        }
    }

    $allowedFilenames = @{}
    if ($null -ne $rule.allowed_filenames) {
        foreach ($filename in $rule.allowed_filenames) {
            $allowedFilenames[$filename] = $true
        }
    }

    $allowedSubdirs = $null
    if ($rule.psobject.properties.match('allowed_subdirectories').Count -gt 0 -and $null -ne $rule.allowed_subdirectories) {
        $allowedSubdirs = @{}
        foreach ($subdir in $rule.allowed_subdirectories) {
            $allowedSubdirs[$subdir] = $true
        }
    }

    $allowRootFiles = $true
    if ($rule.psobject.properties.match('allow_files_at_root').Count -gt 0 -and $null -ne $rule.allow_files_at_root) {
        $allowRootFiles = $rule.allow_files_at_root
    }

    $targetPathUri = [System.Uri]::new(((Resolve-Path -LiteralPath $targetPath).Path.TrimEnd('\') + '\'))

    Get-ChildItem -LiteralPath $targetPath -Recurse -Force -File | ForEach-Object {
        $relativePath = Get-RelativeRepoPath -RepoRoot $repoRoot -AbsolutePath $_.FullName
        $filename = $_.Name
        $extension = $_.Extension.TrimStart('.').ToLowerInvariant()

        if (Test-GitIgnored -RepoRoot $repoRoot -RelativePath $relativePath) {
            return
        }

        if (
            -not $allowedFilenames.ContainsKey($filename) -and
            -not ($extension -and $allowedExtensions.ContainsKey($extension))
        ) {
            $errors.Add("`"$($rule.relative_path)`" contiene `"$relativePath`", pero ahi deben vivir $($rule.description).")
            return
        }

        $fileUri = [System.Uri]::new($_.FullName)
        $relativeToFile = [System.Uri]::UnescapeDataString($targetPathUri.MakeRelativeUri($fileUri).ToString()).Replace('\', '/')
        $pathParts = $relativeToFile -split '/'

        if ($pathParts.Length -eq 1) {
            if (-not $allowRootFiles) {
                $errors.Add("`"$($rule.relative_path)`" no permite archivos sueltos en su raiz. Mueve `"$relativePath`" a un subdirectorio permitido.")
            }
        } elseif ($null -ne $allowedSubdirs) {
            $topDirName = $pathParts[0]
            if (-not $allowedSubdirs.ContainsKey($topDirName)) {
                $errors.Add("Dentro de `"$($rule.relative_path)`", el subdirectorio `"$topDirName`" no esta permitido (`"$relativePath`"). Se permiten: $(($rule.allowed_subdirectories) -join ', ').")
            }
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

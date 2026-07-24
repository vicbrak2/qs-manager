<#
.SYNOPSIS
    Auditoria estatica V1 (../app) vs V2 (./app) para la migracion qs-manager -> qs-manager-v2.
    Fase 1 del plan de migracion (docs/audits/migration-audit.md).

.DESCRIPTION
    Analisis puramente textual (regex sobre el codigo fuente PHP), sin requerir
    PHP CLI ni Composer instalados -- KISS, reproducible en cualquier maquina
    con PowerShell. No ejecuta ni interpreta codigo PHP, solo lo lee como texto.

    Genera:
      - Top archivos por tamano (lineas) en V1 y V2.
      - Conteo de funciones/metodos por archivo (V1 y V2).
      - Referencias a WordPress (wp_*, WP_*, $wpdb, add_action, add_filter,
        register_rest_route, do_action, apply_filters) en V1 y V2 -- V2 DEBE
        dar cero segun las reglas de Fase 0.
      - Clases V1 sin ninguna referencia externa detectada (candidatas a "sin uso").
      - Clases V1 cuyo nombre corto no aparece en ningun archivo de V2 (candidatas
        a "no migradas").

.NOTES
    Aproximado, no exhaustivo: el analisis de "clase sin uso" y "clase no migrada"
    es por nombre corto via grep de texto, no resolucion real de namespaces/autoload.
    Sirve para priorizar la revision manual, no reemplaza el juicio del desarrollador.

.EXAMPLE
    pwsh ./tools/audit-v1-v2.ps1
    pwsh ./tools/audit-v1-v2.ps1 -OutFile docs/audits/migration-audit.md
#>

param(
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot "../..")).Path,
    [string]$V1Path = "app",
    [string]$V2Path = "qs-manager-v2/app",
    [string]$OutFile = "qs-manager-v2/docs/audits/migration-audit.md",
    [int]$TopN = 20
)

$ErrorActionPreference = "Stop"
Set-Location $RepoRoot

$v1Full = Join-Path $RepoRoot $V1Path
$v2Full = Join-Path $RepoRoot $V2Path

if (-not (Test-Path $v1Full)) { throw "No existe V1 path: $v1Full" }
if (-not (Test-Path $v2Full)) { throw "No existe V2 path: $v2Full" }

function Get-PhpFiles($root) {
    Get-ChildItem -Path $root -Recurse -Filter "*.php" -File |
        Where-Object { $_.FullName -notmatch '[\\/](vendor|node_modules|var|dist|graphify-out|test-results|playwright-report)[\\/]' }
}

function Get-FileStats($files) {
    $files | ForEach-Object {
        $lines = (Get-Content $_.FullName -ErrorAction SilentlyContinue)
        $content = $lines -join "`n"
        [PSCustomObject]@{
            Path      = $_.FullName
            Lines     = $lines.Count
            Functions = ([regex]::Matches($content, '\bfunction\s+\w+\s*\(')).Count
        }
    }
}

function Get-ClassNames($files) {
    # nombre corto de cada clase/interface/trait declarada, con el archivo donde vive
    $result = @{}
    foreach ($f in $files) {
        $content = Get-Content $f.FullName -Raw -ErrorAction SilentlyContinue
        if (-not $content) { continue }
        $matches = [regex]::Matches($content, '\b(?:class|interface|trait)\s+(\w+)')
        foreach ($m in $matches) {
            $name = $m.Groups[1].Value
            if (-not $result.ContainsKey($name)) { $result[$name] = @() }
            $result[$name] += $f.FullName
        }
    }
    return $result
}

$wpPatterns = @(
    'wp_\w+', 'WP_\w+', '\$wpdb', 'add_action\s*\(', 'add_filter\s*\(',
    'register_rest_route\s*\(', 'do_action\s*\(', 'apply_filters\s*\('
)

function Count-WpRefs($files) {
    $total = 0
    $byPattern = @{}
    foreach ($pat in $wpPatterns) { $byPattern[$pat] = 0 }
    foreach ($f in $files) {
        $content = Get-Content $f.FullName -Raw -ErrorAction SilentlyContinue
        if (-not $content) { continue }
        foreach ($pat in $wpPatterns) {
            $c = ([regex]::Matches($content, $pat)).Count
            if ($c -gt 0) {
                $byPattern[$pat] += $c
                $total += $c
            }
        }
    }
    return @{ Total = $total; ByPattern = $byPattern }
}

Write-Host "==> Escaneando V1 ($V1Path)..."
$v1Files = Get-PhpFiles $v1Full
$v1Stats = Get-FileStats $v1Files
$v1Classes = Get-ClassNames $v1Files
$v1Wp = Count-WpRefs $v1Files

Write-Host "==> Escaneando V2 ($V2Path)..."
$v2Files = Get-PhpFiles $v2Full
$v2Stats = Get-FileStats $v2Files
$v2Classes = Get-ClassNames $v2Files
$v2Wp = Count-WpRefs $v2Files

Write-Host "==> Buscando clases V1 sin uso detectado..."
# Para cada clase V1, contar referencias a su nombre corto en TODO el arbol V1
# fuera de sus propios archivos de declaracion. Si da 0, es candidata a "sin uso".
$v1AllContent = @{}
foreach ($f in $v1Files) {
    $v1AllContent[$f.FullName] = Get-Content $f.FullName -Raw -ErrorAction SilentlyContinue
}
$unusedV1 = @()
foreach ($className in $v1Classes.Keys) {
    $declFiles = $v1Classes[$className]
    $refCount = 0
    foreach ($path in $v1AllContent.Keys) {
        if ($declFiles -contains $path) { continue }
        $content = $v1AllContent[$path]
        if ($content -and $content -match "\b$([regex]::Escape($className))\b") {
            $refCount++
        }
    }
    if ($refCount -eq 0) {
        $unusedV1 += [PSCustomObject]@{ Class = $className; File = ($declFiles -join ", ") }
    }
}

Write-Host "==> Comparando clases V1 vs V2 (por nombre corto)..."
$notInV2 = @()
foreach ($className in ($v1Classes.Keys | Sort-Object)) {
    if (-not $v2Classes.ContainsKey($className)) {
        $notInV2 += [PSCustomObject]@{ Class = $className; V1File = ($v1Classes[$className] -join ", ") }
    }
}

function RelPath($p) { $p.Substring($RepoRoot.Length + 1).Replace('\', '/') }

$topV1BySize = $v1Stats | Sort-Object Lines -Descending | Select-Object -First $TopN
$topV2BySize = $v2Stats | Sort-Object Lines -Descending | Select-Object -First $TopN
$topV1ByFunc = $v1Stats | Sort-Object Functions -Descending | Select-Object -First $TopN
$topV2ByFunc = $v2Stats | Sort-Object Functions -Descending | Select-Object -First $TopN

$sb = New-Object System.Text.StringBuilder
[void]$sb.AppendLine("# Auditoria V1 vs V2 -- qs-manager")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("Generado por ``tools/audit-v1-v2.ps1`` -- Fase 1 del plan de migracion.")
[void]$sb.AppendLine("Analisis textual/regex, no ejecuta PHP. Reproducible con:")
[void]$sb.AppendLine('```powershell')
[void]$sb.AppendLine("pwsh qs-manager-v2/tools/audit-v1-v2.ps1")
[void]$sb.AppendLine('```')
[void]$sb.AppendLine("")
[void]$sb.AppendLine("## Resumen")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| | V1 (``$V1Path``) | V2 (``$V2Path``) |")
[void]$sb.AppendLine("|---|---|---|")
[void]$sb.AppendLine("| Archivos PHP | $($v1Files.Count) | $($v2Files.Count) |")
[void]$sb.AppendLine("| Lineas totales | $(($v1Stats | Measure-Object Lines -Sum).Sum) | $(($v2Stats | Measure-Object Lines -Sum).Sum) |")
[void]$sb.AppendLine("| Clases/interfaces/traits declarados | $($v1Classes.Count) | $($v2Classes.Count) |")
[void]$sb.AppendLine("| Referencias WordPress detectadas | $($v1Wp.Total) | $($v2Wp.Total) |")
[void]$sb.AppendLine("")
if ($v2Wp.Total -gt 0) {
    [void]$sb.AppendLine("**ALERTA:** V2 tiene $($v2Wp.Total) referencia(s) a WordPress -- viola la regla de Fase 0 (V2 no puede depender de wp_*/WP_*/`$wpdb/hooks).")
} else {
    [void]$sb.AppendLine("OK: V2 no tiene ninguna referencia a WordPress detectada.")
}
[void]$sb.AppendLine("")

[void]$sb.AppendLine("## 1. Archivos por tamano (top $TopN)")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("### V1")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| Lineas | Archivo |")
[void]$sb.AppendLine("|---|---|")
foreach ($r in $topV1BySize) { [void]$sb.AppendLine("| $($r.Lines) | ``$(RelPath $r.Path)`` |") }
[void]$sb.AppendLine("")
[void]$sb.AppendLine("### V2")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| Lineas | Archivo |")
[void]$sb.AppendLine("|---|---|")
foreach ($r in $topV2BySize) { [void]$sb.AppendLine("| $($r.Lines) | ``$(RelPath $r.Path)`` |") }
[void]$sb.AppendLine("")

[void]$sb.AppendLine("## 2. Funciones/metodos por archivo (top $TopN)")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("### V1")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| Funciones | Archivo |")
[void]$sb.AppendLine("|---|---|")
foreach ($r in $topV1ByFunc) { [void]$sb.AppendLine("| $($r.Functions) | ``$(RelPath $r.Path)`` |") }
[void]$sb.AppendLine("")
[void]$sb.AppendLine("### V2")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| Funciones | Archivo |")
[void]$sb.AppendLine("|---|---|")
foreach ($r in $topV2ByFunc) { [void]$sb.AppendLine("| $($r.Functions) | ``$(RelPath $r.Path)`` |") }
[void]$sb.AppendLine("")

[void]$sb.AppendLine("## 3. Referencias a WordPress")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| Patron | V1 | V2 |")
[void]$sb.AppendLine("|---|---|---|")
foreach ($pat in $wpPatterns) {
    [void]$sb.AppendLine("| ``$pat`` | $($v1Wp.ByPattern[$pat]) | $($v2Wp.ByPattern[$pat]) |")
}
[void]$sb.AppendLine("")

[void]$sb.AppendLine("## 4. Clases V1 sin referencia detectada (candidatas a `sin uso`)")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("Aproximado por grep de texto (nombre corto) dentro de todo el arbol V1, excluyendo el propio archivo de declaracion. Puede haber falsos positivos (uso via reflexion, autoload dinamico, string, etc.) -- revisar manualmente antes de borrar.")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| Clase | Archivo |")
[void]$sb.AppendLine("|---|---|")
foreach ($r in ($unusedV1 | Sort-Object Class)) { [void]$sb.AppendLine("| ``$($r.Class)`` | ``$(RelPath $r.File)`` |") }
[void]$sb.AppendLine("")
[void]$sb.AppendLine("Total: $($unusedV1.Count) de $($v1Classes.Count) clases V1.")
[void]$sb.AppendLine("")

[void]$sb.AppendLine("## 5. Clases V1 no presentes en V2 (por nombre corto)")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("Comparacion por nombre corto de clase (no namespace completo) -- una coincidencia de nombre no garantiza que sea la misma responsabilidad, solo indica que no hay ninguna clase con ese nombre en V2 todavia.")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("| Clase V1 | Archivo V1 |")
[void]$sb.AppendLine("|---|---|")
foreach ($r in ($notInV2 | Sort-Object Class)) { [void]$sb.AppendLine("| ``$($r.Class)`` | ``$(RelPath $r.V1File)`` |") }
[void]$sb.AppendLine("")
[void]$sb.AppendLine("Total: $($notInV2.Count) de $($v1Classes.Count) clases V1 no tienen homonimo en V2.")
[void]$sb.AppendLine("")

$outPath = Join-Path $RepoRoot $OutFile
New-Item -ItemType Directory -Force -Path (Split-Path $outPath) | Out-Null
$sb.ToString() | Set-Content -Path $outPath -Encoding UTF8

Write-Host ""
Write-Host "==> Reporte generado: $outPath"
Write-Host "    V1: $($v1Files.Count) archivos, $($v1Classes.Count) clases, $($v1Wp.Total) refs WordPress"
Write-Host "    V2: $($v2Files.Count) archivos, $($v2Classes.Count) clases, $($v2Wp.Total) refs WordPress"
Write-Host "    Clases V1 sin uso detectado: $($unusedV1.Count)"
Write-Host "    Clases V1 no migradas a V2:  $($notInV2.Count)"

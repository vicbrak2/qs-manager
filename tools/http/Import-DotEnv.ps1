param(
    [string] $Path = (Join-Path $PSScriptRoot '..\..\.env')
)

$values = @{}

if (-not (Test-Path -LiteralPath $Path)) {
    return $values
}

foreach ($rawLine in Get-Content -LiteralPath $Path) {
    $line = $rawLine.Trim()

    if ($line -eq '' -or $line.StartsWith('#')) {
        continue
    }

    $match = [regex]::Match($line, '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$')

    if (-not $match.Success) {
        continue
    }

    $key = $match.Groups[1].Value
    $value = $match.Groups[2].Value.Trim()

    if (
        ($value.StartsWith('"') -and $value.EndsWith('"')) -or
        ($value.StartsWith("'") -and $value.EndsWith("'"))
    ) {
        $value = $value.Substring(1, $value.Length - 2)
    }

    $values[$key] = $value
}

return $values

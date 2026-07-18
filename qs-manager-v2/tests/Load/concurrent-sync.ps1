Write-Host "Iniciando prueba de concurrencia: 20 peticiones simultáneas a /import..."

$jobs = @()
for ($i = 1; $i -le 20; $i++) {
    $jobs += Start-ThreadJob -ScriptBlock {
        try {
            $response = Invoke-RestMethod -Uri 'http://localhost:8080/api/v1/sync/sheets/import' -Method Post -SkipHttpErrorCheck -ErrorAction Stop
            return [pscustomobject]@{
                Id = $response.run_id
                Status = $response.status
                Reused = $response.reused
                Message = $response.message
            }
        } catch {
            return [pscustomobject]@{ Error = $_.Exception.Message }
        }
    }
}

$results = $jobs | Wait-Job | Receive-Job
$results | Format-Table

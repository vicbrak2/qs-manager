$ErrorActionPreference = 'Stop'

function Resolve-EnvValue {
    param(
        [string] $Name,
        [string] $Default = ''
    )

    $value = [Environment]::GetEnvironmentVariable($Name)

    if ([string]::IsNullOrWhiteSpace($value)) {
        return $Default
    }

    return $value.Trim()
}

function Resolve-EvolutionApiKey {
    $direct = Resolve-EnvValue -Name 'EVOLUTION_API_KEY'

    if ($direct -ne '') {
        return $direct
    }

    try {
        $envLines = docker inspect -f "{{range .Config.Env}}{{println .}}{{end}}" evolution_api_core 2>$null
        $line = $envLines | Where-Object { $_ -like 'AUTHENTICATION_API_KEY=*' } | Select-Object -First 1

        if ($line) {
            return ($line -replace '^AUTHENTICATION_API_KEY=', '').Trim()
        }
    } catch {
        # best effort fallback
    }

    return ''
}

$baseUrl = Resolve-EnvValue -Name 'EVOLUTION_API_BASE_URL' -Default 'http://localhost:8080'
$baseUrl = $baseUrl.TrimEnd('/')
$apiKey = Resolve-EvolutionApiKey
$instanceName = Resolve-EnvValue -Name 'EVOLUTION_INSTANCE_NAME'

if ($apiKey -eq '') {
    throw 'No se pudo resolver EVOLUTION_API_KEY (ni desde entorno ni desde contenedor evolution_api_core).'
}

$headers = @{
    apikey = $apiKey
    Accept = 'application/json'
}

try {
    $rawInstances = Invoke-RestMethod -Method Get -Uri "$baseUrl/instance/fetchInstances" -Headers $headers -TimeoutSec 20
} catch {
    throw "No se pudo consultar Evolution en $baseUrl/instance/fetchInstances :: $($_.Exception.Message)"
}

$instances = if ($rawInstances -is [System.Array]) { $rawInstances } else { @($rawInstances) }

if ($instances.Count -eq 0) {
    throw 'Evolution no devolvio instancias.'
}

if ($instanceName -eq '') {
    $instanceName = [string]$instances[0].name
}

$instance = $instances | Where-Object { [string]$_.name -eq $instanceName } | Select-Object -First 1

if (-not $instance) {
    throw "La instancia '$instanceName' no existe en Evolution."
}

$status = [string]$instance.connectionStatus
$ready = $status -eq 'open'
$keySuffix = $apiKey.Substring([Math]::Max(0, $apiKey.Length - 4))

"evolution_base_url=$baseUrl"
"instance_name=$instanceName"
"connection_status=$status"
"api_key_suffix=$keySuffix"
"ready=$ready"

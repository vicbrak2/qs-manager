param(
    [string] $N8nBaseUrl = 'http://localhost:5678',
    [string] $QdrantUrl  = 'http://localhost:6333'
)

$ErrorActionPreference = 'Stop'

function Write-Section ([string] $title) {
    Write-Host "`n== $title ==" -ForegroundColor Cyan
}

function Invoke-JsonPost ([string] $Uri, [hashtable] $Body) {
    $json = $Body | ConvertTo-Json -Compress
    curl.exe -sS -X POST `
        -H 'Content-Type: application/json' `
        -d $json `
        $Uri
}

# ── 1. Health checks ──────────────────────────────────────────────────────────
Write-Section "1. Health checks"

Write-Host "n8n  → " -NoNewline
curl.exe -sS -o /dev/null -w "%{http_code}" "$N8nBaseUrl/healthz"

Write-Host "`nQdrant → " -NoNewline
curl.exe -sS -o /dev/null -w "%{http_code}" "$QdrantUrl/healthz"

# ── 2. Qdrant: colección wordpress_context ────────────────────────────────────
Write-Section "2. Colección Qdrant 'wordpress_context'"
curl.exe -sS "$QdrantUrl/collections/wordpress_context" | ConvertFrom-Json | ConvertTo-Json

# ── 3. Webhook ingest (wp-ingest-rag) ────────────────────────────────────────
Write-Section "3. Ingest webhook"
$ingestBody = @{
    post_id      = 999
    title        = '[TEST] Servicio de prueba'
    url          = 'http://localhost/servicios/prueba'
    content      = 'Este es un servicio de prueba para verificar que el pipeline de ingestión RAG funciona correctamente. Precio: $50. Duración: 30 minutos.'
}
Write-Host "POST $N8nBaseUrl/webhook/wp-ingest-rag"
Invoke-JsonPost "$N8nBaseUrl/webhook/wp-ingest-rag" $ingestBody

Start-Sleep -Seconds 2

# ── 4. Webhook chat (wp-chatbot-rag) ─────────────────────────────────────────
Write-Section "4. Chat webhook"
$chatBody = @{
    message    = '¿Qué servicios de prueba tienen disponibles?'
    session_id = "test-session-$(Get-Date -Format 'yyyyMMddHHmmss')"
}
Write-Host "POST $N8nBaseUrl/webhook/wp-chatbot-rag"
$response = Invoke-JsonPost "$N8nBaseUrl/webhook/wp-chatbot-rag" $chatBody
Write-Host "`nRespuesta:"
$response | ConvertFrom-Json | ConvertTo-Json

Write-Host "`n✓ Tests completados" -ForegroundColor Green

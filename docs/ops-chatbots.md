# Operacion de chatbots multi-sitio

Esta capa permite operar varios chatbots con los mismos scripts. Cada sitio se define en
`config/chatbots/sites.json` y los secretos se resuelven desde variables de entorno o archivos
locales ignorados por Git, como `.env` y `tools/n8n/.env.e2e`.

El estado operativo completo del chatbot Qamiluna despues de la migracion a Evolution en Render,
la validacion WhatsApp y la programacion de health/backups esta registrado en
`docs/agents/chatbot-current-state.md`.

## Configuracion por sitio

Cada entrada de `sites.json` declara:

- URL publica de WordPress.
- Variables de entorno donde viven `N8N_BASE_URL`, `N8N_API_KEY`, `EVOLUTION_API_BASE_URL`,
  `EVOLUTION_API_KEY` y `EVOLUTION_INSTANCE_NAME`.
- Servicios Railway asociados a n8n y Postgres.
- Workflows n8n obligatorios.

Para agregar otro cliente:

1. Duplica la entrada `qamiluna`.
2. Cambia `id`, `label`, `wordpressUrl` y nombres de servicios si aplica.
3. Agrega los secretos en `.env`, en un archivo local equivalente o en el entorno del proceso.
4. Ejecuta los comandos usando `-Site nuevo-id`.

## Health check

```powershell
.\tools\chatbots\Test-ChatbotDeployment.ps1 -Site qamiluna
```

Verifica:

- n8n `/healthz`.
- Workflows obligatorios activos.
- Estado de Evolution (`open`).
- Webhook de Evolution activo.
- Home de WordPress respondiendo.

Para salida JSON o alertas:

```powershell
.\tools\chatbots\Test-ChatbotDeployment.ps1 -Site qamiluna -Json
.\tools\chatbots\Test-ChatbotDeployment.ps1 -Site qamiluna -AlertWebhookUrl "https://..."
```

## Export de workflows n8n

```powershell
.\tools\chatbots\Export-N8nWorkflows.ps1 -Site qamiluna -ActiveOnly
```

Guarda snapshots en:

```text
var/backups/chatbots/{site}/n8n-workflows/{timestamp}/
```

La carpeta `var/backups/` esta ignorada por Git.

## Backup logico de Postgres Railway

```powershell
.\tools\chatbots\Backup-RailwayPostgres.ps1 -Site qamiluna
```

El script consulta las variables del servicio Postgres en Railway y genera un dump custom con
`pg_dump`. Si `pg_dump` no esta instalado, usa Docker con la imagen `postgres:16-alpine`.

Salida:

```text
var/backups/chatbots/{site}/postgres/{timestamp}/
```

## Snapshot operativo completo

```powershell
.\tools\chatbots\Invoke-ChatbotOpsSnapshot.ps1 -Site qamiluna
```

Ejecuta health check, export de workflows activos y backup de Postgres.

## Programacion en GitHub Actions

Hay dos workflows programados:

- `.github/workflows/chatbot-health.yml`: cada 30 minutos ejecuta el health check.
  Si falla, abre o comenta un issue con label `ops-alert`.
- `.github/workflows/chatbot-backup.yml`: una vez al dia ejecuta snapshot completo y sube
  los backups como artifact privado del workflow.

Secrets requeridos en GitHub:

- `N8N_API_KEY` o `N8N_QAMILUNA_INSTANCE`
- `EVOLUTION_API_KEY`
- `RAILWAY_API_TOKEN` para backups de Postgres

Variables opcionales en GitHub, porque Qamiluna tiene defaults en los workflows:

- `N8N_BASE_URL`
- `EVOLUTION_API_BASE_URL`
- `EVOLUTION_INSTANCE_NAME`

Si GitHub CLI tiene permisos de administracion de Actions secrets:

```powershell
gh secret set N8N_API_KEY
gh secret set EVOLUTION_API_KEY
gh secret set RAILWAY_API_TOKEN
```

## Politica de secretos

- No commitear `.env`, `.env.e2e`, dumps ni exports.
- Los workflows versionados no deben incluir claves literales.
- Preferir variables de entorno por sitio.
- `N8N_ENCRYPTION_KEY` no se rota sin plan de migracion y backup previo.


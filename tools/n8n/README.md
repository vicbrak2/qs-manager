# Tools n8n

Scripts operativos para inspeccionar, probar y ajustar la integración local con n8n.

## Convención

- `tools/n8n/`: scripts ejecutables y datos de soporte local.
- `infrastructure/n8n/`: `docker-compose`, workflows JSON y documentación de despliegue.
- `var/tmp/n8n/`: salidas temporales, snapshots y dumps locales no versionados.

## Scripts actuales

- `bulk_ingest.js`: ingesta masiva de documentos hacia el webhook `wp-ingest-rag`.
- `test_webhook.js`: smoke test directo del webhook de ingesta.
- `get_wfs.js`: lista workflows vía API de n8n.
- `sync_workflows.js`: hace upsert de los workflows versionados del repo en una instancia remota de n8n.
- `get_chatbot_trigger.js`: inspecciona nodos clave del workflow del chatbot.
- `check_errors.js`: imprime el detalle de la ejecución más reciente.
- `apply_text_splitter.js`: agrega el splitter al workflow objetivo si aún no existe.
- `create_ingest_wf.js`: crea un workflow de ingesta vía API.
- `update_all_workflows.js`: aplica actualizaciones masivas a workflows.
- `fix_n8n.js` y `fix_n8n.php`: helpers experimentales para ajustes por API.

Varios de estos scripts leen `N8N_CHATBOT_TOKEN` desde `.env` y asumen n8n local en `http://localhost:5678`.

Para sincronizar contra la instancia remota de Qamiluna desde tu máquina, `sync_workflows.js` acepta:

- `N8N_API_KEY`: API key preferida para CI o uso manual.
- `N8N_QAMILUNA_INSTANCE`: alias local para el API key de `https://n8n.qamilunastudio.com`.
- `N8N_BASE_URL`: opcional si quieres apuntar a otra instancia distinta.

Si la instancia remota aún no tiene credenciales, el sync puede crearlas automáticamente cuando `.env` incluya:

- `QDRANT_URL` o `QDRANT_CLUSTER_ENDPOINT`
- `QDRANT_API_KEY` o `QGRANT_KEY`
- `HUGGING_FACE_API_KEY`
- `OPENROUTER_API_KEY`
- `GROQ_N8N_API_KEY`

## Deploy continuo

El workflow de GitHub Actions despliega el plugin por FTP y, en `push` a `main`, sincroniza estos archivos en la instancia remota de `n8n`:

- `infrastructure/n8n/chatbot_rag_workflow.json`
- `infrastructure/n8n/wp_rag_ingestion_workflow.json`

Secretos y variables esperados en GitHub:

- `N8N_API_KEY` o `N8N_QAMILUNA_INSTANCE` en `Settings -> Secrets and variables -> Actions -> Secrets`
- `N8N_BASE_URL` opcional en `Variables`
- `N8N_QDRANT_CREDENTIAL_NAME` opcional en `Variables`
- `N8N_HUGGING_FACE_CREDENTIAL_NAME` opcional en `Variables`
- `N8N_OPENROUTER_CREDENTIAL_NAME` opcional en `Variables`

Si no defines los nombres de credencial, el script intentará usar estos defaults:

- `Qdrant account`
- `Hugging Face direct`
- `OpenRouter account`

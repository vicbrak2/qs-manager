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
- `get_chatbot_trigger.js`: inspecciona nodos clave del workflow del chatbot.
- `check_errors.js`: imprime el detalle de la ejecución más reciente.
- `apply_text_splitter.js`: agrega el splitter al workflow objetivo si aún no existe.
- `create_ingest_wf.js`: crea un workflow de ingesta vía API.
- `update_all_workflows.js`: aplica actualizaciones masivas a workflows.
- `fix_n8n.js` y `fix_n8n.php`: helpers experimentales para ajustes por API.

Varios de estos scripts leen `N8N_CHATBOT_TOKEN` desde `.env` y asumen n8n local en `http://localhost:5678`.

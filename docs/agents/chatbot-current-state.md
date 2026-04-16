# Estado actual del chatbot Qamiluna

Fecha de registro: 2026-04-15

Este documento registra el estado operativo posterior a la migracion de Evolution API, la
reactivacion de WhatsApp y la incorporacion de herramientas modulares de operacion. No contiene
secretos ni tokens.

## Objetivo operativo

Mantener un chatbot reutilizable para Qamiluna Studio y futuros sitios, con estos canales:

- Chat web de WordPress.
- WhatsApp entrante y saliente.
- RAG sobre contenido del sitio.
- Health checks, backups y alertas programadas.

## Arquitectura vigente

### Chat web

```text
Widget WordPress
  -> POST /wp-json/qs/v1/agents/chat
  -> ChatbotGateway
  -> n8n WordPress RAG Chatbot
  -> Qdrant knowledge_base
  -> WordPress response_blocks
  -> Widget WordPress
```

WordPress conserva la primera capa de control:

- Validacion de mensaje.
- Resolucion de `session_id`.
- Quick replies.
- Flujo guiado de reserva.
- Historial corto por sesion.
- Cache de respuestas.
- Fallback si n8n falla.
- Registro de conversaciones y feedback.

### WhatsApp

```text
WhatsApp
  -> Evolution API
  -> n8n WhatsApp Inbound Bridge
  -> WordPress /wp-json/qs/v1/agents/chat
  -> n8n WhatsApp Hybrid Router
  -> Evolution API
  -> WhatsApp
```

El inbound bridge solo procesa mensajes individuales validos:

- Evento `messages.upsert`.
- No procesa `fromMe`.
- No procesa grupos `@g.us`.
- No procesa broadcast.
- Extrae texto desde mensajes simples, extended text, captions, botones y listas.

El router hibrido envia por Evolution API cuando el mensaje no es critico. La ruta Meta API queda
preparada para mensajes criticos con `esCritico=true`.

## Servicios desplegados

- WordPress: `https://qamilunastudio.com`
- n8n: `https://n8n.qamilunastudio.com`
- Evolution API: `https://qamiluna-evolution-api.onrender.com`
- Evolution instance: `qamiluna-test`
- Estado esperado Evolution: `open`
- Railway mantiene n8n y PostgreSQL.
- Render mantiene Evolution API.

## Workflows n8n activos

- `WordPress RAG Chatbot`
- `WP RAG Ingestion`
- `WhatsApp Hybrid Router`
- `WhatsApp Inbound Bridge`

Tambien existe un workflow de captura QR de Evolution usado durante la vinculacion inicial.

## RAG y modelo actual

Workflow: `infrastructure/n8n/chatbot_rag_workflow.json`

- Webhook: `POST /webhook/wp-chatbot-rag`
- Agente: LangChain `toolsAgent`
- Modelo: OpenRouter `nvidia/nemotron-3-super-120b-a12b:free`
- Temperatura: `0.1`
- Memoria: `Window Buffer Memory`
- Ventana de memoria: `4`
- Session key: `body.session_id`
- Vector store: Qdrant
- Coleccion: `wordpress_context`
- Tool: `knowledge_base`
- `topK`: `2`
- Embeddings: HuggingFace `sentence-transformers/distilbert-base-nli-mean-tokens`

El prompt actual esta personalizado para Qamiluna Studio: tono chileno, respuestas breves,
restricciones de precios/talleres y flujo conversacional de reserva.

## Operacion modular agregada

Commit base: `16e443d Add modular chatbot ops tooling`

Archivos principales:

- `config/chatbots/sites.json`
- `tools/chatbots/Test-ChatbotDeployment.ps1`
- `tools/chatbots/Export-N8nWorkflows.ps1`
- `tools/chatbots/Backup-RailwayPostgres.ps1`
- `tools/chatbots/Invoke-ChatbotOpsSnapshot.ps1`
- `tools/chatbots/ChatbotOps.psm1`
- `docs/ops-chatbots.md`

La configuracion por sitio permite duplicar `qamiluna` y crear nuevos chatbots con nombres de
servicio, URLs y variables de entorno distintas.

## Automatizacion programada

Commit base: `ac96239 Schedule chatbot health checks and backups`

Workflows GitHub Actions:

- `.github/workflows/chatbot-health.yml`
  - Corre cada 30 minutos.
  - Verifica n8n, workflows activos, Evolution open, webhook y WordPress.
  - Si falla, abre o comenta un issue con label `ops-alert`.
- `.github/workflows/chatbot-backup.yml`
  - Corre una vez al dia.
  - Exporta workflows n8n.
  - Ejecuta dump logico de PostgreSQL Railway.
  - Sube el snapshot como artifact privado por 30 dias.

Secrets necesarios en GitHub Actions:

- `N8N_API_KEY`
- `EVOLUTION_API_KEY`
- `RAILWAY_API_TOKEN`

## Validaciones realizadas

### Health check GitHub Actions

Commit final de endurecimiento: `08f5c09 Harden Evolution health check fallback`

Resultado:

- `Chatbot Health`: success.
- n8n responde.
- Workflows requeridos activos.
- Evolution instance en estado `open`.
- Webhook Evolution activo.
- WordPress responde.

El health check usa `/connectionState` y, si ese endpoint se demora desde GitHub runners, cae a
`/instance/fetchInstances`.

### Snapshot local

Resultado validado:

- Health check OK.
- Export de 5 workflows OK.
- Dump PostgreSQL OK.
- Backups locales bajo `var/backups/chatbots/qamiluna/`.
- `var/backups/` ignorado por Git.

### WhatsApp desplegado

Prueba final exitosa:

- Flujo: n8n Railway -> Evolution Render -> WhatsApp.
- Destino de prueba: numero configurado por el operador.
- n8n execution: `54846`.
- Estado execution: `success`.
- Evolution instance: `qamiluna-test`.
- Estado final Evolution: `open`.
- Message id: `3EB07E53A66BBBE693BFC2`.

Antes de la prueba exitosa hubo un timeout en envio, resuelto reiniciando solo la instancia
Evolution con:

```text
POST /instance/restart/qamiluna-test
```

## Decisiones vigentes

- No operar local para produccion; todo debe funcionar desplegado.
- Mantener n8n y PostgreSQL en Railway.
- Mantener Evolution API en Render porque la vinculacion WhatsApp funciono ahi.
- No rotar tokens expuestos en chat por ahora, por decision operativa del usuario.
- Mantener secretos fuera de Git.
- Evolucionar hacia perfiles por sitio para soportar multiples chatbots.

## Riesgos y mejoras pendientes

- El prompt y algunas reglas siguen siendo especificas de Qamiluna si no se define un perfil por sitio.
- El modelo gratuito de OpenRouter puede tener latencia o disponibilidad variable.
- Falta limpiar workflows temporales de QR si ya no se usan.

## Perfil multi-sitio

El chatbot ahora resuelve un perfil por sitio desde `config/chatbots/profiles.json`, o desde
`QS_CHATBOT_PROFILE_JSON` si se quiere sobreescribir sin tocar codigo.

Cada perfil define:

- `site_id`
- `brand_name`
- `locale`
- `tone`
- `whatsapp_url`
- `aliases`
- `services`
- `booking_fields`
- `restrictions`
- `vector_collection`
- `retrieval_top_k`

WordPress envia este perfil a n8n en cada request junto con `site_id`, `channel`,
`vector_collection`, `retrieval_top_k`, `session_id` e historial curado.

La memoria conversacional confiable queda en WordPress. n8n recibe el historial ya preparado en
`body.history` y no mantiene memoria propia del agente para evitar duplicacion de estado.

## Siguiente fase propuesta

Antes de optimizar respuestas, convertir el chatbot a un patron multi-sitio:

1. Reindexar contenido para asegurar metadata `site_id` en Qdrant.
2. Medir calidad de respuestas con preguntas reales por canal.
3. Ajustar perfiles por sitio antes de duplicar a nuevos clientes.
4. Evaluar modelo pago/estable si el modelo gratuito presenta latencia.

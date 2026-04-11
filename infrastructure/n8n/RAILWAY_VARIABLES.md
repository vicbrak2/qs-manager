# Variables de Railway - GUÍA DE SEGURIDAD

## ⚠️ IMPORTANTE
- NO copies archivos .env al repositorio
- NO compartas credenciales en Git
- Usa Railway UI para agregar variables privadas

## Cómo agregar variables en Railway (SEGURO)

1. Ve a: https://railway.app/dashboard
2. Selecciona tu proyecto `qs-manager`
3. Click en el servicio `n8n`
4. Pestaña: "Variables"
5. Click en "RAW Editor" o "Add Variable"

## Variables para n8n en Railway

```
N8N_HOST=n8n.qamilunastudio.com
N8N_PORT=8080
N8N_PROTOCOL=https
WEBHOOK_URL=https://n8n.qamilunastudio.com/
GENERIC_TIMEZONE=America/Santiago
N8N_PROXY_HOPS=1
NODE_ENV=production
QDRANT_URL=https://3d2e2314-92fc-4e65-9ede-dd5202acdaca.eu-west-1-0.aws.cloud.qdrant.io
QDRANT_API_KEY=[NUEVA_KEY_REGENERADA]
EVOLUTION_API_BASE_URL=https://evolution.qamilunastudio.com
EVOLUTION_INSTANCE_NAME=qamiluna-test
EVOLUTION_API_KEY=[NUEVA_KEY_REGENERADA]
```

## Variables sensibles - REGENERA ESTAS

Antes de agregar a Railway, regenera:

1. **QDRANT_API_KEY**:
   - Ve a: https://cloud.qdrant.io
   - Cluster → API Keys
   - Revoca la key anterior expuesta
   - Genera nueva
   - Copia aquí

2. **N8N_CHATBOT_TOKEN** (si lo necesitas):
   - En n8n local: Settings → API Tokens
   - Regenera token
   - Copia aquí

3. **GROQ_N8N_API_KEY**:
   - https://console.groq.com/keys
   - Revoca la key expuesta
   - Genera nuevo
   - Copia aquí

4. **HUGGING_FACE_API_KEY**:
   - https://huggingface.co/settings/tokens
   - Revoca el token expuesto
   - Genera nuevo
   - Copia aquí

## Pasos en Railway UI

1. Click en "Add Variable"
2. Campo "Name": `QDRANT_API_KEY`
3. Campo "Value": [pega la nueva key]
4. Click "Add"
5. Repite para cada variable

## NO hagas esto:
❌ Crear archivo .env en el repo
❌ Escribir credenciales en Dockerfile
❌ Compartir variables en chats/mensajes

## SÍ haz esto:
✅ Agrega variables en Railway UI (encriptadas)
✅ Usa .gitignore para excluir .env
✅ Regenera credenciales antes de cada deploy

## Verificar que está seguro

Después de configurar variables:
```bash
# En tu repo local
git log --all --oneline -- .env
# Si sale resultado, las credenciales están en historial Git
```

Si aparecen en el historial, necesitamos limpiar con `git filter-branch` o `BFG`.

# Guía: Agregar Variables en Railway (SEGURO)

## Paso 1: Accede a Railway

1. Ve a: https://railway.app/dashboard
2. Selecciona tu proyecto: `qs-manager`
3. Selecciona el servicio: `n8n`

## Paso 2: Abre el editor de variables

Opción A (Recomendado - RAW Editor):
1. Click en "Variables"
2. Click en el botón "RAW Editor" (arriba a la derecha)
3. Ves un editor de texto JSON/YAML

Opción B (UI Simple):
1. Click en "Variables"
2. Click en "Add Variable"

## Paso 3: Agrega variables (una por una)

Si usas RAW Editor, pega esto (reemplaza [VALUE] con tus nuevas keys):

```
N8N_HOST=n8n.qamilunastudio.com
N8N_PORT=8080
N8N_PROTOCOL=https
WEBHOOK_URL=https://n8n.qamilunastudio.com/
GENERIC_TIMEZONE=America/Santiago
N8N_PROXY_HOPS=1
NODE_ENV=production
QDRANT_URL=https://3d2e2314-92fc-4e65-9ede-dd5202acdaca.eu-west-1-0.aws.cloud.qdrant.io
QDRANT_API_KEY=[NUEVA_KEY_DE_QDRANT]
GROQ_N8N_API_KEY=[NUEVA_KEY_DE_GROQ]
HUGGING_FACE_API_KEY=[NUEVA_KEY_DE_HF]
N8N_CHATBOT_TOKEN=[NUEVO_TOKEN_N8N]
```

Si usas Add Variable:
1. Name: `QDRANT_API_KEY`
2. Value: [pega nueva key]
3. Click "Add"
4. Repite para cada variable

## Paso 4: Verifica que están encriptadas

- Después de agregar, las variables deben mostrar como `••••••` (ocultas)
- Si ves el valor en texto plano, algo está mal

## Paso 5: Redeploy

1. Click en "Deploy"
2. Select "Latest commit"
3. Click "Deploy"
4. Espera a que complete

## Paso 6: Verifica que funciona

Una vez deployed:
```bash
curl https://n8n.qamilunastudio.com/healthz
```

Debería retornar:
```json
{"status": 200, "message": "ok"}
```

## NO hagas esto en Railway

❌ Copiar/pegar archivo .env
❌ Escribir credenciales en README
❌ Usar valores hardcoded en Dockerfile
❌ Compartir screenshots con variables visibles

## Sí haz esto

✅ Agrega cada variable en Railway UI
✅ Railway encripta automáticamente
✅ Usa nombres descriptivos (GROQ_API_KEY, no groq_api_key_version_2)
✅ Documenta qué hace cada variable

## Si algo falla en deployment

Mira los logs:
1. Click en "Deployments" (arriba)
2. Selecciona el deployment fallido
3. Click en "Logs" (arriba)
4. Busca errores como:
   - `QDRANT_API_KEY not set` → La variable no se agregó
   - `Connection timeout` → URL de Qdrant incorrecta
   - `Authentication failed` → Key incorrecta

## ¿Las variables están disponibles en n8n?

Dentro de n8n, accede a ellas en workflows así:

```
{{ $env.QDRANT_API_KEY }}
{{ $env.GROQ_N8N_API_KEY }}
```

O en Node.js:
```javascript
process.env.QDRANT_API_KEY
process.env.GROQ_N8N_API_KEY
```

# 🚀 Cómo Agregar Variables en Railway - Paso a Paso

## PASO 1: Abre Railway UI

1. Ve a: https://railway.app/dashboard
2. Selecciona proyecto: `qs-manager`
3. Haz click en "Variables" (pestaña superior)

## PASO 2: Click en "Raw Editor"

Arriba a la derecha verás:
- "Shared Variable"
- "Raw Editor" ← **CLICK AQUÍ**
- "+ New Variable"

## PASO 3: Copia este bloque completo

Copia TODO esto (sin los corchetes de ejemplo):

```
N8N_HOST=n8n.qamilunastudio.com
N8N_PORT=8080
N8N_PROTOCOL=https
WEBHOOK_URL=https://n8n.qamilunastudio.com/
GENERIC_TIMEZONE=America/Santiago
N8N_PROXY_HOPS=1
NODE_ENV=production
QDRANT_URL=https://3d2e2314-92fc-4e65-9ede-dd5202acdaca.eu-west-1-0.aws.cloud.qdrant.io
QDRANT_API_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9[NUEVA_KEY_AQUI]
GROQ_N8N_API_KEY=gsk_[NUEVA_KEY_AQUI]
HUGGING_FACE_API_KEY=hf_[NUEVA_KEY_AQUI]
N8N_CHATBOT_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9[NUEVO_TOKEN_AQUI]
```

## PASO 4: Pega en el editor

En el Raw Editor, borra lo que esté y pega el bloque anterior.

## PASO 5: Click "Save" o "Update"

Un botón debería aparecer abajo a la derecha.

## PASO 6: Verifica que están ocultas

Las variables deben mostrar como `••••••` (no en texto plano).

## PASO 7: Redeploy

1. Click en "Deployments" (arriba)
2. Click en "Latest" o "Deploy"
3. Espera a que termine (2-5 minutos)

## PASO 8: Verifica que funciona

```bash
curl https://n8n.qamilunastudio.com/healthz
```

---

## ⚠️ IMPORTANTE: ANTES de pegar, tienes que tener las nuevas keys

### Qdrant
1. https://cloud.qdrant.io
2. Selecciona tu cluster
3. Settings → API Keys
4. Crea NUEVA key (revoca la antigua)
5. Copia el valor

### Groq
1. https://console.groq.com/keys
2. Revoca la antigua
3. Crea nueva
4. Copia el valor

### Hugging Face
1. https://huggingface.co/settings/tokens
2. Revoca la antigua
3. Crea nuevo token
4. Copia el valor

### N8N Token
1. En tu n8n local: Menú → Settings → API Tokens
2. Revoca el antiguo
3. Crea nuevo (nombre: "Railway")
4. Copia el valor

---

## Si algo falla

Revisa los logs:
1. Click en "Deployments"
2. Selecciona el deployment fallido
3. Click en "Logs"
4. Busca errores

Errores comunes:
- `QDRANT_API_KEY not set` → No se agregó la variable
- `Connection refused` → URL incorrecta
- `Authentication failed` → API key incorrecta

---

## ✅ Checklist final

- [ ] Creé nueva QDRANT_API_KEY
- [ ] Creé nueva GROQ_N8N_API_KEY
- [ ] Creé nueva HUGGING_FACE_API_KEY
- [ ] Creé nuevo N8N_CHATBOT_TOKEN
- [ ] Pegué todas las variables en Railway Raw Editor
- [ ] Hice click en "Save"
- [ ] Hice click en "Deploy"
- [ ] Esperé a que termine el deploy
- [ ] Probé: `curl https://n8n.qamilunastudio.com/healthz`
- [ ] ✅ ¡Funciona!

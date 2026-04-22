# 🚀 Fix Railway: Crear Servicio Separado para N8N

## ⚠️ El Problema
- Railway está desplegando el repo `qs-manager` (WordPress) como si fuera n8n
- Por eso ves FrankenPHP en lugar de n8n
- Necesitas un servicio SEPARADO solo para la imagen de Docker n8n

## ✅ La Solución: 3 Pasos

---

## PASO 1: Elimina el servicio actual (qs-manager)

1. En Railway Dashboard → Proyecto `qs-manager`
2. Click en el servicio actual (que muestra FrankenPHP)
3. Click en los "3 puntos" (arriba derecha)
4. "Remove Service"
5. Confirma

**Resultado:** El proyecto debe quedar vacío

---

## PASO 2: Crea nuevo servicio SOLO para N8N

1. En el proyecto `qs-manager` (vacío)
2. Click "+ New Service"
3. Selecciona "Docker Image"
4. En "Image" pega:
   ```
   docker.n8n.io/n8nio/n8n:latest
   ```
5. Click "Deploy"

**Espera a que construya** (30-60 segundos)

---

## PASO 3: Configura Variables del Nuevo Servicio

Una vez que el servicio de n8n esté "Active":

1. Click en el servicio n8n
2. Pestaña "Variables"
3. Click "Raw Editor"
4. **BORRA todo y pega ESTO:**

```json
{
  "N8N_PORT": "${{ PORT }}",
  "N8N_PROTOCOL": "https",
  "N8N_HOST": "n8n.qamilunastudio.com",
  "WEBHOOK_URL": "https://n8n.qamilunastudio.com/",
  "N8N_PROXY_HOPS": "1",
  "GENERIC_TIMEZONE": "America/Santiago",
  "NODE_ENV": "production",
  "QDRANT_URL": "https://3d2e2314-92fc-4e65-9ede-dd5202acdaca.eu-west-1-0.aws.cloud.qdrant.io",
  "QDRANT_API_KEY": "NUEVA_KEY_QDRANT_REGENERADA",
  "GROQ_N8N_API_KEY": "NUEVA_KEY_GROQ_REGENERADA",
  "HUGGING_FACE_API_KEY": "NUEVA_KEY_HF_REGENERADA"
}
```

**IMPORTANTE:**
- Reemplaza los 3 valores con keys NUEVAS (regeneradas)
- NO agregues `N8N_CHATBOT_TOKEN` aún
- NO pongas contraseña/usuario

5. Click "Update Variables"
6. Espera a que redeploy automáticamente

---

## PASO 4: Configura Dominio

1. En el servicio n8n
2. Click "Settings" (arriba)
3. "Domains"
4. Click "+ Add Domain"
5. Ingresa: `n8n.qamilunastudio.com`
6. Railway te dará un CNAME (ej: `n8n-production-xxxx.railway.app`)

**En Cloudflare:**
1. Ve a tu zona `qamilunastudio.com`
2. Busca el record CNAME `n8n` (ya existe del paso anterior)
3. Cambia el Target al CNAME que te dio Railway
4. Proxy: OFF (Grey Cloud)

---

## ✅ Verificar que Funciona

Una vez todo configurado:

```bash
# Debería retornar 200 OK después de 1-2 minutos
curl https://n8n.qamilunastudio.com/healthz
```

Si retorna 200, n8n está listo.

---

## 📌 Diferencia IMPORTANTE

| Antes (Problema) | Ahora (Correcto) |
|------------------|-----------------|
| Repo: qs-manager | Repo: NINGUNO |
| Imagen: Detectada de GitHub | Imagen: docker.n8n.io/n8nio/n8n:latest |
| Resultado: FrankenPHP ❌ | Resultado: N8N ✅ |

---

## 🔐 Regenera estas 3 keys ANTES de pegar:

| Key | Dónde |
|-----|-------|
| QDRANT_API_KEY | https://cloud.qdrant.io → Cluster → Settings → API Keys |
| GROQ_N8N_API_KEY | https://console.groq.com/keys |
| HUGGING_FACE_API_KEY | https://huggingface.co/settings/tokens |

**Revoca las antiguas** (que ya fueron expuestas públicamente).

---

## 📝 Checklist

- [ ] Eliminé el servicio anterior (FrankenPHP)
- [ ] Creé nuevo servicio con Docker Image
- [ ] Pegué imagen: docker.n8n.io/n8nio/n8n:latest
- [ ] Deploy completado (Active ✅)
- [ ] Regeneré 3 API keys nuevas
- [ ] Pegué JSON de variables (sin N8N_CHATBOT_TOKEN)
- [ ] Updated Variables → Redeploy automático
- [ ] Agregué dominio n8n.qamilunastudio.com
- [ ] Actualicé CNAME en Cloudflare
- [ ] Esperé 2 minutos
- [ ] Probé curl /healthz → 200 OK

---

## ⏱️ Tiempo total: ~10 minutos

1. Eliminar servicio: 1 min
2. Crear + Deploy n8n: 2 min
3. Configurar variables: 2 min
4. Configurar dominio: 2 min
5. Propagación DNS + verificación: 3 min

**Total: ~10 minutos**

---

## 🚨 Si algo falla:

Comparte:
1. URL de Railway que aparece
2. Status del servicio (Active/Building/Crashed)
3. Cualquier error en Deploy Logs

Luego lo arreglamos.

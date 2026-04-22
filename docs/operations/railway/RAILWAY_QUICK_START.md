# 🚀 Archivo LISTO para Subir a Railway

## Lo que necesitas hacer:

### 1. **Copia el bloque de variables** (infrastructure/.env.railway.example)

```
N8N_HOST=n8n.qamilunastudio.com
N8N_PORT=8080
N8N_PROTOCOL=https
WEBHOOK_URL=https://n8n.qamilunastudio.com/
GENERIC_TIMEZONE=America/Santiago
N8N_PROXY_HOPS=1
NODE_ENV=production
QDRANT_URL=https://3d2e2314-92fc-4e65-9ede-dd5202acdaca.eu-west-1-0.aws.cloud.qdrant.io
QDRANT_API_KEY=[REGENERATE_FROM_https://cloud.qdrant.io]
GROQ_N8N_API_KEY=[REGENERATE_FROM_https://console.groq.com/keys]
HUGGING_FACE_API_KEY=[REGENERATE_FROM_https://huggingface.co/settings/tokens]
N8N_CHATBOT_TOKEN=[REGENERATE_FROM_n8n_LOCAL_SETTINGS]
```

### 2. **Antes de pegar, regenera estas 4 keys:**

| Servicio | Dónde regenerar | Tiempo |
|----------|----------------|--------|
| Qdrant | https://cloud.qdrant.io → Settings → API Keys | 1 min |
| Groq | https://console.groq.com/keys | 1 min |
| Hugging Face | https://huggingface.co/settings/tokens | 1 min |
| N8N | n8n local → Settings → API Tokens | 1 min |

### 3. **En Railway UI:**

1. https://railway.app/dashboard
2. Proyecto: `qs-manager`
3. Pestaña: "Variables"
4. Click: "Raw Editor"
5. **PEGA** el bloque (con las nuevas keys)
6. Click: "Save"
7. Click: "Deploy" (arriba)

### 4. **Verifica que funciona:**

```bash
curl https://n8n.qamilunastudio.com/healthz
```

---

## 📂 Archivos de referencia en tu repo:

- `infrastructure/.env.railway.example` - Variables (COPIA/PEGA)
- `infrastructure/n8n/RAILWAY_SETUP_STEPS.md` - Pasos detallados
- `infrastructure/n8n/RAILWAY_ADD_VARIABLES.md` - Cómo agregar en UI
- `infrastructure/n8n/RAILWAY_DEPLOYMENT.md` - Guía completa
- `infrastructure/n8n/RAILWAY_VARIABLES.md` - Documentación de variables
- `docs/SECURITY_CHECKLIST.md` - Checklist de seguridad

---

## ✅ Resumen rápido:

1. **Regenera 4 credenciales** (5 min)
2. **Pega en Railway Variables** (1 min)
3. **Deploy** (5 min)
4. **Prueba** (1 min)

**Total: ~15 minutos**

---

## 🔐 Recuerda:

- ✅ Nunca subas `.env` a Git
- ✅ Usa Railway UI para variables (encriptadas automáticamente)
- ✅ Revoca credenciales antiguas
- ✅ Verifica logs si algo falla

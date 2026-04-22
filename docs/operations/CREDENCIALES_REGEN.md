# 🔐 Regeneración de Credenciales para Railway N8N

## ⚠️ IMPORTANTE
Las siguientes credenciales fueron expuestas públicamente.
**DEBES regenerar nuevas antes de agregarlas a Railway.**

---

## 1️⃣ QDRANT_API_KEY

**Dónde regenerar:**
1. Ve a: https://cloud.qdrant.io
2. Login con tu cuenta
3. Selecciona tu cluster: `3d2e2314-92fc-4e65-9ede-dd5202acdaca` (eu-west-1)
4. Click "Settings" (arriba)
5. Pestaña "API Keys"
6. Busca tu key actual (es un JWT muy largo)
7. Click en el icono de 🗑️ para REVOCAR
8. Click "+ Create API Key"
9. Dale un nombre: `Railway N8N`
10. Click "Create"
11. **COPIA la nueva key** (es muy larga, toda en una línea)

**Verificación:**
- Nueva key debería empezar con: `eyJh...` (JWT)
- Longitud: ~200 caracteres

---

## 2️⃣ GROQ_N8N_API_KEY

**Dónde regenerar:**
1. Ve a: https://console.groq.com/keys
2. Login
3. Busca tu key actual expuesta
4. Click el icono de 🗑️ para REVOCAR
5. Click "+ Create New API Key"
6. Dale un nombre: `n8n-railway`
7. Click "Create"
8. **COPIA la nueva key** (empieza con `gsk_`)

**Verificación:**
- Nueva key empieza con: `gsk_`
- Longitud: ~40 caracteres

---

## 3️⃣ HUGGING_FACE_API_KEY

**Dónde regenerar:**
1. Ve a: https://huggingface.co/settings/tokens
2. Login
3. Busca tu token actual expuesto
4. Click el icono de 🗑️ para REVOCAR
5. Click "+ Create new token"
6. Dale un nombre: `n8n-railway-embedding`
7. Permisos: "Read" es suficiente
8. Click "Create token"
9. **COPIA el nuevo token**

**Verificación:**
- Nuevo token empieza con: `hf_`
- Longitud: ~30-40 caracteres

---

## 📋 Checklist de Regeneración

- [ ] Qdrant API Key regenerada y copiada
- [ ] Groq API Key regenerada y copiada
- [ ] Hugging Face token regenerado y copiado
- [ ] Antiguas keys revocadas (no pueden volver a usarse)
- [ ] Tengo los 3 valores nuevos listos en un bloc de notas

---

## 📝 Dónde Guardar Temporalmente

Abre un editor de texto (Notepad) y copia esto:

```
QDRANT_API_KEY=
[PEGA AQUI LA NUEVA KEY]

GROQ_N8N_API_KEY=
[PEGA AQUI LA NUEVA KEY]

HUGGING_FACE_API_KEY=
[PEGA AQUI LA NUEVA KEY]
```

**NUNCA guardes esto en Git ni lo compartas.**
Úsalo solo para pegar en Railway.

---

## 🔄 Orden de Pasos

1. Regenera las 3 keys (toma ~5 min)
2. Cópialas en un bloc de notas temporal
3. Ve a Railway
4. Crea nuevo servicio de Docker Image (n8n)
5. Pega el JSON con las 3 nuevas keys
6. Deploy
7. **Borra el bloc de notas** (no lo necesitas más)

---

## ✅ Una vez que tengas las 3 nuevas keys

Vuelve al archivo: `RAILWAY_FIX_N8N_SEPARATE_SERVICE.md`

Y sigue los pasos para crear el servicio separado en Railway.

---

## 🚨 Importante

- NO guardes credenciales en archivos `.env` del repo
- NO las compartas en chats/mensajes
- Úsalas UNA VEZ en Railway
- Luego olvídalas

Railway las encripta automáticamente en su base de datos.

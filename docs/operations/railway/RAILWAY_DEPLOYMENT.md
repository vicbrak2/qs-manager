# Guía de Despliegue en Railway

## 1. Copia los pasos en Railway

### A. Conectar repositorio (recomendado)
1. Ve a https://railway.app/dashboard
2. Click en "New Project" → "Deploy from GitHub"
3. Selecciona tu repositorio `qs-manager`
4. Railway detectará automáticamente el `Dockerfile`

### B. O usando Docker Image directamente
1. "New Project" → "Docker Image"
2. Imagen: `docker.n8n.io/n8nio/n8n:latest`

## 2. Variables de Entorno en Railway UI

En el dashboard de tu proyecto n8n:
1. Click en "Variables"
2. Agrega estas variables (copiar de `.env.railway`):

```
N8N_HOST=n8n.qamilunastudio.com
N8N_PORT=8080
N8N_PROTOCOL=https
WEBHOOK_URL=https://n8n.qamilunastudio.com/
QDRANT_URL=https://3d2e2314-92fc-4e65-9ede-dd5202acdaca.eu-west-1-0.aws.cloud.qdrant.io
GENERIC_TIMEZONE=America/Santiago
N8N_PROXY_HOPS=1
NODE_ENV=production
```

3. Si Qdrant requiere API Key:
   - Agrega: `QDRANT_API_KEY=tu-clave-aqui`

## 3. Configurar Dominio Personalizado

En Railway (tab "Settings" del servicio):
1. "Domains" → "Add Domain"
2. Ingresa: `n8n.qamilunastudio.com`
3. Railway te dará un CNAME (ej: `something.railway.app`)

En Cloudflare:
1. Ve a tu zona DNS
2. Agrega registro CNAME:
   - Nombre: `n8n`
   - Target: `[valor que te da Railway]`
   - Proxy: OFF (Grey cloud, no Orange)

## 4. Volúmenes/Almacenamiento

Railway proporciona almacenamiento persistente automáticamente.
- Los datos de n8n se guardan en `/home/node/.n8n`

## 5. Verificar Despliegue

Después de hacer deploy:
```bash
curl https://n8n.qamilunastudio.com/healthz
```

Debería retornar `200 OK`

## 6. Migrar datos (opcional)

Si quieres copiar tu base de datos local a Railway:
1. Exporta desde tu n8n local: Settings → Export
2. En Railway n8n: Settings → Import

## Notas:
- Railway cobra por uso (CPU, memoria, almacenamiento)
- El plan gratuito tiene limitaciones
- Si usas Qdrant Cloud (AWS), el costo es separado

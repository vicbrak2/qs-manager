# ✅ Checklist de Seguridad Post-Exposición

## 1. Credenciales Expuestas
Las siguientes fueron compartidas públicamente:

- [ ] DB MySQL: `[REDACTED_PASSWORD]`
- [ ] API Groq: `gsk_[REDACTED_EXPOSED_KEY]`
- [ ] API Hugging Face: `hf_[REDACTED_EXPOSED_TOKEN]`
- [ ] Token n8n: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`
- [ ] API Key Qdrant: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`
- [ ] Contraseña FTP: `[REDACTED_PASSWORD]`
- [ ] Credenciales WP: usuario/pass (revisar si es publica o privada)
- [ ] Token ngrok: `[REDACTED_AUTHTOKEN]`

## 2. REGENERAR Inmediatamente

### MySQL
```bash
# En tu hosting:
1. Ve a cPanel/Plesk
2. MySQL Users
3. Cambia la contraseña del usuario MySQL afectado
4. Nueva contraseña: [GENERA SEGURA CON 32 CARACTERES]
5. Actualiza en WordPress wp-config.php
```

### Groq API
```
1. Ve a: https://console.groq.com/keys
2. Revoca la key expuesta
3. Crea nueva key
4. Guarda en Railway Variables → GROQ_N8N_API_KEY
```

### Hugging Face
```
1. https://huggingface.co/settings/tokens
2. Revoca el token expuesto
3. Crea nuevo token
4. Guarda en Railway Variables → HUGGING_FACE_API_KEY
```

### Qdrant API Key
```
1. https://cloud.qdrant.io → Tu cluster
2. Settings → API Keys
3. Revoca la actual
4. Crea nueva
5. Guarda en Railway Variables → QDRANT_API_KEY
```

### n8n Tokens
```
1. n8n local: Settings → API Tokens
2. Revoca el actual
3. Crea nuevo: "Railway Deployment"
4. Guarda en Railway Variables → N8N_CHATBOT_TOKEN
```

### ngrok
```
1. https://dashboard.ngrok.com/auth/your-authtoken
2. Revoca el authtoken expuesto
3. Genera nuevo
4. Actualiza cloudflared si usa ngrok
```

### FTP
```
1. Tu hosting (cPanel/Plesk)
2. FTP Accounts
3. Cambia la password del usuario FTP afectado
4. Nueva password: [32 caracteres seguros]
```

## 3. Verificar Git

- [x] Credenciales NO están en historial
- [x] .gitignore creado
- [ ] Verificar no hay credenciales en commits recientes

```bash
git log -p --all | grep -i "password\|api_key\|token" | head -5
```

## 4. Actualizar Railway

- [ ] Agregar TODAS las nuevas keys en Railway Variables (UI)
- [ ] Verificar que NO hay credenciales en Dockerfile
- [ ] Verificar que NO hay credenciales en docker-compose.yml
- [ ] Redeploy después de cambiar variables

## 5. Verificaciones Finales

```bash
# ¿Credenciales en repo?
git grep -i "password\|api_key\|token" | grep -v node_modules

# ¿Archivos .env?
find . -name ".env*" -not -path "./node_modules/*"

# ¿Historial Git limpio?
git log --all --oneline | wc -l
```

## 6. Contraseñas Seguras - GENERA CON ESTO

Usa una contraseña de 32 caracteres con:
- Mayúsculas
- Minúsculas
- Números
- Caracteres especiales

Ejemplo seguro:
```
X7$kP2@mQ9!yR4#wL8&vN6*tB3^cF1%jH5
```

O genera online (seguro):
- https://www.random.org/passwords/
- https://www.1password.com/password-generator/

## 7. Timeline

- [ ] Hoy: Regenerar todas las keys
- [ ] Hoy: Agregar a Railway
- [ ] Hoy: Verificar funcionamiento
- [ ] Mañana: Monitorear logs en caso de acceso no autorizado

## 8. Nota Importante

Las credenciales compartidas en chats/mensajes son **permanentemente públicas**.
Considera esto un "incident" y regenera cualquier cosa crítica.

Para el futuro:
- Usa Railway Secrets (no archivos .env)
- Usa variables de entorno de tu CI/CD
- Nunca compartas credenciales en texto plano

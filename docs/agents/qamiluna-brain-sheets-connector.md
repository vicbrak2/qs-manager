# Guia para conectar el agente del estudio con Sheets

Esta guia explica como darle al agente interno `qamiluna_team` acceso controlado a la planilla de seguimiento contable para consultar valores de traslado y servicios activos.

## 1. Fuente de datos

El acceso no debe hacerse directo desde el modelo. El modelo debe recibir datos vivos desde Brain, y Brain debe pedirlos a un Web App de Google Apps Script.

Apps Script fuente en este repo:

- `tools/qs-manager-v2-webapp.gs`

Spreadsheets usados:

- Bitacora / seguimiento: `1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE`
- Hoja de seguimiento: `Bitácora QS — Servicios`
- Catalogo maestro: `Servicios_Master`
- Planilla contable: `1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE`
- Hoja contable de servicios: `Servicios`

## 2. Acciones disponibles en el Web App

### `list_active_services`

Devuelve los servicios activos desde `Servicios_Master`.

Request:

```json
{
  "action": "list_active_services",
  "api_key": "QS_MANAGER_READ_API_KEY"
}
```

Respuesta:

```json
{
  "ok": true,
  "result": {
    "generated_at": "2026-07-23T...",
    "source": {
      "spreadsheet_id": "1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE",
      "sheet": "Servicios_Master"
    },
    "count": 3,
    "services": [
      {
        "service_id": "SVC-0001",
        "nombre_canonico": "Social: Maquillaje",
        "categoria": "Social",
        "tipo": "servicio",
        "cantidad": 1,
        "precio_venta": 45000,
        "costo_total": 15000,
        "utilidad": 30000,
        "margen": 0.66,
        "estado_margen": "AZUL",
        "source_sheet": "Servicios",
        "source_row": 5,
        "notes": ""
      }
    ]
  }
}
```

Regla: si la columna `activo` existe y vale `false`, `no`, `0`, `inactivo` o `inactiva`, el servicio queda fuera.

### `get_transport_values`

Devuelve valores de traslado registrados en `Bitácora QS — Servicios`, agrupados por comuna y con filas de evidencia.

Request por comuna:

```json
{
  "action": "get_transport_values",
  "api_key": "QS_MANAGER_READ_API_KEY",
  "comuna": "Providencia",
  "limit": 50
}
```

Request para todas las comunas:

```json
{
  "action": "get_transport_values",
  "api_key": "QS_MANAGER_READ_API_KEY",
  "limit": 200
}
```

Respuesta:

```json
{
  "ok": true,
  "result": {
    "generated_at": "2026-07-23T...",
    "source": {
      "spreadsheet_id": "1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE",
      "sheet": "Bitácora QS — Servicios"
    },
    "filters": {
      "comuna": "Providencia",
      "include_cancelled": false
    },
    "count": 8,
    "groups": [
      {
        "comuna": "Providencia",
        "count": 8,
        "latest_date": "2026-07-20",
        "transport_values": [
          { "value": 5000, "count": 6 },
          { "value": 7000, "count": 2 }
        ],
        "sample_references": ["Agenda: Julio!24"]
      }
    ],
    "rows": [
      {
        "row": 24,
        "id": "QS-123",
        "fecha": "2026-07-20",
        "servicio": "Social: Maquillaje",
        "clienta": "Nombre clienta",
        "comuna": "Providencia",
        "traslado": 5000,
        "estado_servicio": "Pendiente",
        "referencia_agenda": "Agenda: Julio!24"
      }
    ],
    "truncated": false
  }
}
```

Por defecto excluye servicios cancelados. Para auditoria:

```json
{
  "action": "get_transport_values",
  "api_key": "QS_MANAGER_READ_API_KEY",
  "include_cancelled": true
}
```

## 3. Despliegue en Google Apps Script

1. Abrir el proyecto Apps Script que publica el Web App de QS Manager V2.
2. Reemplazar o actualizar el contenido con `tools/qs-manager-v2-webapp.gs`.
3. En `Project Settings > Script properties`, configurar:

```text
QS_MANAGER_READ_API_KEY=<clave larga para lectura>
QS_MANAGER_CATALOG_API_KEY=<clave existente para alta de servicios, si ya se usa>
```

Si `QS_MANAGER_READ_API_KEY` no existe, el script acepta temporalmente `QS_MANAGER_CATALOG_API_KEY` para lectura. Lo recomendable es usar una clave separada.

4. Deploy:

```text
Deploy > Manage deployments > Edit > New version > Deploy
```

5. Copiar la URL del Web App. Debe quedar en Brain como variable:

```text
QS_MANAGER_GAS_URL=https://script.google.com/macros/s/.../exec
QS_MANAGER_READ_API_KEY=<misma clave de lectura>
```

## 4. Pruebas manuales

GET para listar servicios:

```text
https://script.google.com/macros/s/.../exec?action=list_active_services&api_key=CLAVE
```

GET para traslado por comuna:

```text
https://script.google.com/macros/s/.../exec?action=get_transport_values&api_key=CLAVE&comuna=Providencia&limit=20
```

POST recomendado desde Brain:

```bash
curl -X POST "$QS_MANAGER_GAS_URL" \
  -H "Content-Type: application/json" \
  -d '{"action":"get_transport_values","api_key":"'$QS_MANAGER_READ_API_KEY'","comuna":"Providencia","limit":50}'
```

## 5. Implementacion en Brain

Agregar un conector nuevo en `brain/connectors.py`, por ejemplo `qs_manager_qamiluna`.

Variables esperadas:

```text
QS_MANAGER_GAS_URL
QS_MANAGER_READ_API_KEY
```

Comportamiento sugerido:

1. Pedir siempre `list_active_services`.
2. Pedir `get_transport_values` sin comuna con `limit: 200`, o con comuna si Brain puede detectar una comuna en la pregunta.
3. Cachear 10 minutos, igual que los conectores de Instagram.
4. Inyectar el resultado al prompt como `DATOS EN VIVO`.
5. Si falla el conector, indicar error del conector y no inventar valores.

Formato recomendado para el bloque de contexto:

```text
DATOS EN VIVO - QS MANAGER
Generado: <timestamp>

SERVICIOS ACTIVOS
- SVC-0001 | Social: Maquillaje | Categoria: Social | Precio: 45000 | Costo: 15000 | Margen: 0.66

TRASLADOS REGISTRADOS
- Providencia: 5000 (6 registros), 7000 (2 registros). Ultimo registro: 2026-07-20.
- Las Condes: 8000 (4 registros). Ultimo registro: 2026-07-18.

Notas:
- Los valores provienen de Bitácora QS — Servicios.
- Servicios cancelados excluidos por defecto.
- Si una comuna tiene mas de un valor, no hay tarifa unica confirmada.
```

## 6. Instrucciones para el perfil `qamiluna_team`

Agregar al YAML del perfil:

```text
Cuando respondas sobre servicios activos, valores de servicio o traslados, usa solo el bloque DATOS EN VIVO - QS MANAGER.

Servicios activos:
- Usa la lista SERVICIOS ACTIVOS.
- Si el servicio no aparece, di que no aparece como activo en la planilla.
- No inventes precios, costos ni margenes.

Traslados:
- Usa TRASLADOS REGISTRADOS.
- Si hay una comuna especifica, busca esa comuna.
- Si existe un solo valor frecuente, puedes decir "en los registros aparece $X".
- Si existen varios valores para la misma comuna, muestra los valores y aclara que no hay tarifa unica confirmada.
- Si no hay registros para la comuna, dilo y pide confirmar manualmente.

Seguridad:
- No reveles datos personales de clientas.
- No muestres telefonos, direcciones ni nombres completos salvo que el equipo lo pida explicitamente para operar una reserva concreta.
- No modifiques la planilla desde el chat.
```

## 7. Preguntas ejemplo para validar el agente

```text
Que servicios activos tenemos en la planilla?
```

```text
Cuanto hemos cobrado de traslado en Providencia?
```

```text
Hay tarifa unica de traslado para Las Condes?
```

```text
Existe el servicio Social: M+P como activo?
```

Respuesta esperada: siempre debe citar que el dato viene de QS Manager/Bitacora y debe aclarar cuando hay datos multiples o faltantes.

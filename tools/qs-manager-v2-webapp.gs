const QS_MANAGER_V2_BITACORA_ID = '1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE';
const QS_MANAGER_V2_BITACORA_SHEET = 'Bitácora QS — Servicios';
const QS_MANAGER_V2_SERVICES_MASTER_SHEET = 'Servicios_Master';
const QS_MANAGER_V2_SCHEMA_VERSION = '2.0';

const QS_MANAGER_V2_HEADERS = [
  'ID',
  'Fecha',
  'Hora',
  'Tipo',
  'Encargada',
  'Servicio',
  'Tipo de Servicio',
  'Clienta',
  'Comuna',
  'Traslado',
  'Abono',
  'Valor Servicio',
  'Total Servicio',
  'Saldo',
  'Forma de Pago',
  'Estado Pago',
  'Estado Servicio',
  'Observaciones',
  'Dirección',
  'ID Calendar',
  'Referencia Agenda',
  'Mes',
  'Servicio original',
  'Estado homologación',
  'ID Contrato',
  'Hito',
  'Grupo Caja',
  'Rol Caja',
  'Estado Contrato',
  'Reserva Contrato',
  'Nota Caja',
  'booking_id',
  'service_id',
  'servicio_nombre_snapshot',
  'Teléfono',
  'schema_version',
  'last_qs_sync_at',
  'sync_source',
];

function doPost(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(10000);

  try {
    const payload = JSON.parse(e.postData && e.postData.contents ? e.postData.contents : '{}');
    const result = upsertQsManagerV2Booking_(payload);

    return ContentService
      .createTextOutput(JSON.stringify({ ok: true, result }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: false, error: String(error && error.message ? error.message : error) }))
      .setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}

function upsertQsManagerV2Booking_(payload) {
  if (!payload || !payload.id) {
    throw new Error('Missing booking id.');
  }

  const spreadsheet = SpreadsheetApp.openById(QS_MANAGER_V2_BITACORA_ID);
  const sheet = spreadsheet.getSheetByName(QS_MANAGER_V2_BITACORA_SHEET) || spreadsheet.getSheets()[0];
  const service = resolveQsManagerV2Service_(spreadsheet, payload);
  ensureQsManagerV2Headers_(sheet);

  const externalId = String(payload.booking_id || payload.id);
  const existingRow = findQsManagerV2RowById_(sheet, externalId);
  const targetRow = existingRow || sheet.getLastRow() + 1;
  const action = existingRow ? 'update' : 'append';
  const values = QS_MANAGER_V2_HEADERS.map((header) => valueForQsManagerV2Header_(header, payload, externalId, service));

  sheet.getRange(targetRow, 1, 1, values.length).setValues([values]);

  return {
    action,
    row: targetRow,
    id: externalId,
  };
}

function resolveQsManagerV2Service_(spreadsheet, payload) {
  const explicitServiceId = payload.service_id || payload.serviceId;
  const serviceName = payload.servicio || payload.service_name || payload.nombre_servicio || '';
  const master = spreadsheet.getSheetByName(QS_MANAGER_V2_SERVICES_MASTER_SHEET);

  if (!master) {
    return {
      service_id: explicitServiceId || '',
      nombre_canonico: serviceName,
    };
  }

  const rows = master.getDataRange().getValues();
  if (rows.length < 2) {
    return {
      service_id: explicitServiceId || '',
      nombre_canonico: serviceName,
    };
  }

  const headers = rows[0].map((header) => normalizeQsManagerV2Key_(header));
  const serviceIdIndex = headers.indexOf('service_id');
  const nameIndex = headers.indexOf('nombre_canonico');
  const activeIndex = headers.indexOf('activo');

  for (let i = 1; i < rows.length; i += 1) {
    const row = rows[i];
    const rowServiceId = serviceIdIndex >= 0 ? String(row[serviceIdIndex] || '') : '';
    const rowName = nameIndex >= 0 ? String(row[nameIndex] || '') : '';
    const rowActive = activeIndex >= 0 ? row[activeIndex] : true;

    if (rowActive === false) {
      continue;
    }

    if (explicitServiceId && rowServiceId === String(explicitServiceId)) {
      return {
        service_id: rowServiceId,
        nombre_canonico: rowName,
      };
    }

    if (serviceName && normalizeQsManagerV2Key_(rowName) === normalizeQsManagerV2Key_(serviceName)) {
      return {
        service_id: rowServiceId,
        nombre_canonico: rowName,
      };
    }
  }

  return {
    service_id: explicitServiceId || '',
    nombre_canonico: serviceName,
  };
}

function ensureQsManagerV2Headers_(sheet) {
  const width = QS_MANAGER_V2_HEADERS.length;
  const current = sheet.getRange(1, 1, 1, width).getValues()[0];
  const missing = current.every((value) => value === '');

  if (missing) {
    sheet.getRange(1, 1, 1, width).setValues([QS_MANAGER_V2_HEADERS]);
  }
}

function findQsManagerV2RowById_(sheet, externalId) {
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    return null;
  }

  const ids = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
  for (let i = 0; i < ids.length; i += 1) {
    if (String(ids[i][0]) === externalId) {
      return i + 2;
    }
  }

  return null;
}

function valueForQsManagerV2Header_(header, payload, externalId, service) {
  const month = payload.fecha ? String(payload.fecha).slice(0, 7) : '';
  const now = new Date();

  const values = {
    ID: externalId,
    Fecha: payload.fecha || '',
    Hora: payload.hora || '',
    Tipo: payload.tipo || 'Servicio',
    Encargada: payload.encargada || payload.staff_name || '',
    Servicio: service.nombre_canonico || payload.servicio || payload.service_name || '',
    'Tipo de Servicio': payload.tipo_servicio || '',
    Clienta: payload.clienta || payload.customer_name || '',
    Comuna: payload.comuna || '',
    Traslado: payload.traslado || 0,
    Abono: payload.abono || 0,
    'Valor Servicio': payload.valor_servicio || 0,
    'Total Servicio': payload.total_servicio || 0,
    Saldo: payload.saldo || 0,
    'Forma de Pago': payload.forma_pago || '',
    'Estado Pago': payload.estado_pago || '',
    'Estado Servicio': payload.estado_servicio || payload.status || '',
    Observaciones: payload.observaciones || '',
    Dirección: payload.direccion || '',
    'ID Calendar': payload.id_calendar || '',
    'Referencia Agenda': payload.referencia_agenda || '',
    Mes: month,
    'Servicio original': payload.servicio || payload.service_name || '',
    'Estado homologación': service.service_id ? 'homologado' : 'sin_service_id',
    'ID Contrato': payload.id_contrato || '',
    Hito: payload.hito || '',
    'Grupo Caja': payload.grupo_caja || '',
    'Rol Caja': payload.rol_caja || '',
    'Estado Contrato': payload.estado_contrato || '',
    'Reserva Contrato': payload.reserva_contrato || '',
    'Nota Caja': payload.nota_caja || '',
    booking_id: externalId,
    service_id: service.service_id || '',
    servicio_nombre_snapshot: service.nombre_canonico || payload.servicio || payload.service_name || '',
    Teléfono: payload.telefono || payload.customer_phone || '',
    schema_version: payload.schema_version || QS_MANAGER_V2_SCHEMA_VERSION,
    last_qs_sync_at: now,
    sync_source: payload.source || 'qs-manager-v2',
  };

  return Object.prototype.hasOwnProperty.call(values, header) ? values[header] : '';
}

function normalizeQsManagerV2Key_(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ');
}

const QS_MANAGER_V2_BITACORA_ID = '1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE';
const QS_MANAGER_V2_ACCOUNTING_ID = '1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE';
const QS_MANAGER_V2_BITACORA_SHEET = 'Bitácora QS — Servicios';
const QS_MANAGER_V2_SERVICES_MASTER_SHEET = 'Servicios_Master';
const QS_MANAGER_V2_ACCOUNTING_SERVICES_SHEET = 'Servicios';
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
    const result = handleQsManagerV2Action_(payload);

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

function qsManagerV2DoGet_(e) {
  try {
    const params = e && e.parameter ? e.parameter : {};
    const payload = Object.assign({}, params, {
      include_cancelled: params.include_cancelled === 'true',
      limit: params.limit ? Number(params.limit) : undefined,
    });
    const result = handleQsManagerV2Action_(payload);

    return ContentService
      .createTextOutput(JSON.stringify({ ok: true, result }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: false, error: String(error && error.message ? error.message : error) }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

function handleQsManagerV2Action_(payload) {
  const action = String(payload && payload.action ? payload.action : '').trim();
  if (action === 'create_service') return createQsManagerV2Service_(payload);
  if (action === 'list_active_services' || action === 'list_quote_services') {
    return listQsManagerV2ActiveAccountingServices_(payload);
  }
  if (action === 'get_transport_values') return getQsManagerV2TransportValues_(payload);
  return upsertQsManagerV2Booking_(payload);
}

function createQsManagerV2Service_(payload) {
  validateQsManagerCatalogKey_(payload.api_key);
  const requestId = String(payload.idempotency_key || '').trim();
  const service = payload.service || {};
  if (!requestId) throw new Error('Missing idempotency_key.');

  const properties = PropertiesService.getScriptProperties();
  const cached = properties.getProperty('service-write:' + requestId);
  if (cached) return JSON.parse(cached);

  const name = String(service.name || '').trim();
  const category = String(service.category || '').trim();
  const quantity = positiveIntegerQs_(service.quantity, 'quantity');
  const salePrice = nonNegativeIntegerQs_(service.sale_price, 'sale_price');
  const totalCost = nonNegativeIntegerQs_(service.total_cost, 'total_cost');
  if (name.length < 3) throw new Error('Service name must contain at least 3 characters.');
  if (totalCost > salePrice) throw new Error('Total cost cannot exceed sale price.');

  const bitacora = SpreadsheetApp.openById(QS_MANAGER_V2_BITACORA_ID);
  const master = bitacora.getSheetByName(QS_MANAGER_V2_SERVICES_MASTER_SHEET);
  if (!master) throw new Error('Servicios_Master sheet was not found.');

  const existing = findMasterServiceByNameQs_(master, name);
  if (existing) throw new Error('A service with the same canonical name already exists.');

  const accountingBook = SpreadsheetApp.openById(QS_MANAGER_V2_ACCOUNTING_ID);
  const accounting = accountingBook.getSheetByName(QS_MANAGER_V2_ACCOUNTING_SERVICES_SHEET);
  if (!accounting) throw new Error('Accounting Servicios sheet was not found.');

  const accountingRow = Math.max(5, accounting.getLastRow() + 1);
  const serviceId = nextServiceIdQs_(master);
  const utility = salePrice - totalCost;
  const margin = salePrice === 0 ? 0 : utility / salePrice;
  const marginStatus = marginStatusQs_(margin);
  let accountingWritten = false;

  try {
    accounting.getRange(accountingRow, 1, 1, 21).setValues([[
      totalCost > 0 ? 'Sí' : 'No', category, name, quantity, salePrice, 0,
      0, 0, 0, 0, 0, '', '', '', totalCost, '', '', '', '', '',
      'Creado desde QS Manager V2. Costo registrado en Otros costos; desglose pendiente.',
    ]]);
    accountingWritten = true;

    // Preserve the accounting formulas and validation pattern used by the sheet.
    const templateRow = Math.max(5, accountingRow - 1);
    accounting.getRange(templateRow, 13, 1, 8).copyTo(
      accounting.getRange(accountingRow, 13, 1, 8),
      SpreadsheetApp.CopyPasteType.PASTE_FORMULA,
      false
    );
    accounting.getRange(templateRow, 1, 1, 21).copyTo(
      accounting.getRange(accountingRow, 1, 1, 21),
      SpreadsheetApp.CopyPasteType.PASTE_DATA_VALIDATION,
      false
    );
    SpreadsheetApp.flush();

    const masterRow = master.getLastRow() + 1;
    master.getRange(masterRow, 1, 1, 14).setValues([[
      serviceId, name, category, 'servicio', quantity, salePrice, totalCost,
      utility, margin, marginStatus, totalCost > 0, 'Servicios', accountingRow,
      'Alta manual desde QS Manager V2',
    ]]);

    const result = {
      action: 'created',
      service_id: serviceId,
      master_row: masterRow,
      accounting_row: accountingRow,
      margin_status: marginStatus,
    };
    properties.setProperty('service-write:' + requestId, JSON.stringify(result));
    return result;
  } catch (error) {
    if (accountingWritten) accounting.deleteRow(accountingRow);
    throw error;
  }
}

function validateQsManagerCatalogKey_(providedKey) {
  const configured = PropertiesService.getScriptProperties().getProperty('QS_MANAGER_CATALOG_API_KEY');
  if (!configured) throw new Error('QS_MANAGER_CATALOG_API_KEY is not configured.');
  if (String(providedKey || '') !== configured) throw new Error('Invalid catalog API key.');
}

function validateQsManagerReadKey_(providedKey) {
  const properties = PropertiesService.getScriptProperties();
  const configured = properties.getProperty('QS_MANAGER_READ_API_KEY')
    || properties.getProperty('QS_MANAGER_CATALOG_API_KEY');
  if (!configured) throw new Error('QS_MANAGER_READ_API_KEY is not configured.');
  if (String(providedKey || '') !== configured) throw new Error('Invalid read API key.');
}

function listQsManagerV2ActiveAccountingServices_(payload) {
  validateQsManagerReadKey_(payload.api_key);

  const spreadsheet = SpreadsheetApp.openById(QS_MANAGER_V2_ACCOUNTING_ID);
  const sheet = spreadsheet.getSheetByName(QS_MANAGER_V2_ACCOUNTING_SERVICES_SHEET);
  if (!sheet) throw new Error('Accounting Servicios sheet was not found.');

  const rows = sheet.getDataRange().getValues();
  if (rows.length < 2) {
    return {
      generated_at: new Date(),
      source: {
        spreadsheet_id: QS_MANAGER_V2_ACCOUNTING_ID,
        sheet: QS_MANAGER_V2_ACCOUNTING_SERVICES_SHEET,
      },
      count: 0,
      services: [],
    };
  }

  const header = findQsManagerV2Header_(rows, ['activo', 'servicio']);
  const columns = header
    ? indexQsManagerV2Headers_(rows[header.index])
    : {};
  const firstDataRow = header ? header.index + 1 : 4;
  const services = [];
  const seen = {};

  for (let index = firstDataRow; index < rows.length; index += 1) {
    const row = rows[index];
    if (row.every((value) => value === '' || value == null)) continue;
    const active = booleanQsManagerV2Value_(
      valueQsManagerV2_(row, columns, ['activo', 'active'], 0),
      true
    );
    if (!active) continue;

    const serviceName = stringQsManagerV2Value_(
      valueQsManagerV2_(row, columns, ['servicio', 'nombre', 'nombre_canonico', 'nombre canonico'], 2)
    );
    const serviceKey = normalizeQsManagerV2Key_(serviceName);
    if (!serviceName || seen[serviceKey]) continue;
    seen[serviceKey] = true;

    const serviceType = stringQsManagerV2Value_(
      valueQsManagerV2_(row, columns, ['tipo de servicio', 'tipo', 'categoria', 'categoría'], 1)
    );
    const salePrice = integerQsManagerV2Value_(
      valueQsManagerV2_(row, columns, ['valor', 'precio venta', 'precio_venta', 'valor servicio', 'total servicio'], 4)
    );

    services.push({
      service_id: '',
      nombre_canonico: serviceName,
      label: serviceName,
      tipo_servicio: serviceType,
      category: serviceType,
      sale_price: salePrice,
      source_sheet: QS_MANAGER_V2_ACCOUNTING_SERVICES_SHEET,
      source_row: index + 1,
    });
  }

  const serviceTypes = ['Todos'].concat(Object.keys(services.reduce((types, service) => {
    if (service.tipo_servicio) types[service.tipo_servicio] = true;
    return types;
  }, {})).sort());

  return {
    generated_at: new Date(),
    source: {
      spreadsheet_id: QS_MANAGER_V2_ACCOUNTING_ID,
      sheet: QS_MANAGER_V2_ACCOUNTING_SERVICES_SHEET,
    },
    count: services.length,
    service_types: serviceTypes,
    services,
  };
}

function getQsManagerV2TransportValues_(payload) {
  validateQsManagerReadKey_(payload.api_key);

  const spreadsheet = SpreadsheetApp.openById(QS_MANAGER_V2_BITACORA_ID);
  const sheet = spreadsheet.getSheetByName(QS_MANAGER_V2_BITACORA_SHEET) || spreadsheet.getSheets()[0];
  const rows = sheet.getDataRange().getValues();
  if (rows.length < 2) {
    return {
      generated_at: new Date(),
      source: {
        spreadsheet_id: QS_MANAGER_V2_BITACORA_ID,
        sheet: sheet.getName(),
      },
      count: 0,
      groups: [],
      rows: [],
    };
  }

  const columns = indexQsManagerV2Headers_(rows[0]);
  const requestedComuna = normalizeQsManagerV2Key_(payload.comuna || payload.commune || '');
  const includeCancelled = payload.include_cancelled === true;
  const limit = positiveLimitQsManagerV2_(payload.limit, 200);
  const records = [];
  const groups = {};

  for (let index = 1; index < rows.length; index += 1) {
    const row = rows[index];
    if (row.every((value) => value === '' || value == null)) continue;

    const comuna = stringQsManagerV2Value_(valueQsManagerV2_(row, columns, ['comuna'], 8));
    if (requestedComuna && normalizeQsManagerV2Key_(comuna) !== requestedComuna) continue;

    const estadoServicio = stringQsManagerV2Value_(valueQsManagerV2_(row, columns, ['estado servicio', 'estado_servicio'], 16));
    if (!includeCancelled && normalizeQsManagerV2Key_(estadoServicio).includes('cancel')) continue;

    const traslado = numberOrBlankQsManagerV2_(valueQsManagerV2_(row, columns, ['traslado', 'transporte'], 9));
    if (traslado === '') continue;

    const fecha = valueQsManagerV2_(row, columns, ['fecha'], 1);
    const record = {
      row: index + 1,
      id: stringQsManagerV2Value_(valueQsManagerV2_(row, columns, ['id'], 0)),
      fecha,
      servicio: stringQsManagerV2Value_(valueQsManagerV2_(row, columns, ['servicio'], 5)),
      clienta: stringQsManagerV2Value_(valueQsManagerV2_(row, columns, ['clienta'], 7)),
      comuna,
      traslado,
      estado_servicio: estadoServicio,
      referencia_agenda: stringQsManagerV2Value_(valueQsManagerV2_(row, columns, ['referencia agenda', 'referencia_agenda'], 20)),
    };
    records.push(record);

    const groupKey = normalizeQsManagerV2Key_(comuna) || '(sin comuna)';
    if (!groups[groupKey]) {
      groups[groupKey] = {
        comuna: comuna || 'Sin comuna',
        values: {},
        count: 0,
        latest_date: '',
        sample_references: [],
      };
    }
    groups[groupKey].count += 1;
    groups[groupKey].values[String(traslado)] = (groups[groupKey].values[String(traslado)] || 0) + 1;
    groups[groupKey].latest_date = latestQsManagerV2Date_(groups[groupKey].latest_date, fecha);
    if (groups[groupKey].sample_references.length < 5 && record.referencia_agenda) {
      groups[groupKey].sample_references.push(record.referencia_agenda);
    }
  }

  const grouped = Object.keys(groups)
    .sort()
    .map((key) => ({
      comuna: groups[key].comuna,
      count: groups[key].count,
      latest_date: groups[key].latest_date,
      transport_values: Object.keys(groups[key].values)
        .sort((left, right) => Number(left) - Number(right))
        .map((value) => ({
          value: Number(value),
          count: groups[key].values[value],
        })),
      sample_references: groups[key].sample_references,
    }));

  return {
    generated_at: new Date(),
    source: {
      spreadsheet_id: QS_MANAGER_V2_BITACORA_ID,
      sheet: sheet.getName(),
    },
    filters: {
      comuna: payload.comuna || payload.commune || '',
      include_cancelled: includeCancelled,
    },
    count: records.length,
    groups: grouped,
    rows: records.slice(0, limit),
    truncated: records.length > limit,
  };
}

function findMasterServiceByNameQs_(sheet, name) {
  const normalizedName = normalizeQsManagerV2Key_(name);
  const rows = sheet.getDataRange().getValues();
  for (let index = 1; index < rows.length; index += 1) {
    if (normalizeQsManagerV2Key_(rows[index][1]) === normalizedName) return index + 1;
  }
  return null;
}

function nextServiceIdQs_(sheet) {
  const values = sheet.getLastRow() < 2 ? [] : sheet.getRange(2, 1, sheet.getLastRow() - 1, 1).getValues();
  const max = values.reduce((current, row) => {
    const match = String(row[0] || '').match(/^SVC-(\d+)$/i);
    return match ? Math.max(current, Number(match[1])) : current;
  }, 0);
  return 'SVC-' + String(max + 1).padStart(4, '0');
}

function nonNegativeIntegerQs_(value, field) {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed < 0) throw new Error(field + ' must be a non-negative integer.');
  return parsed;
}

function positiveIntegerQs_(value, field) {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed <= 0) throw new Error(field + ' must be a positive integer.');
  return parsed;
}

function marginStatusQs_(margin) {
  if (margin >= 0.4) return 'AZUL';
  if (margin >= 0.3) return 'VERDE';
  if (margin < 0.2) return 'ROJO';
  return 'AMARILLO';
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
  const existingRow = findQsManagerV2RowByIdentity_(sheet, externalId, payload);
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

function findQsManagerV2RowByIdentity_(sheet, externalId, payload) {
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    return null;
  }

  const rows = sheet.getRange(1, 1, lastRow, Math.max(sheet.getLastColumn(), QS_MANAGER_V2_HEADERS.length)).getValues();
  const columns = indexQsManagerV2Headers_(rows[0]);
  const reference = stringQsManagerV2Value_(payload.referencia_agenda || payload.agenda_reference || '');
  const calendarId = stringQsManagerV2Value_(payload.id_calendar || payload.calendar_event_id || '');
  const candidates = [
    ['referencia agenda', reference],
    ['id calendar', calendarId],
    ['booking_id', externalId],
    ['id', externalId],
  ];

  for (let candidateIndex = 0; candidateIndex < candidates.length; candidateIndex += 1) {
    const header = candidates[candidateIndex][0];
    const expected = candidates[candidateIndex][1];
    const column = columns[normalizeQsManagerV2Key_(header)];
    if (!expected || column == null) continue;

    for (let i = 1; i < rows.length; i += 1) {
      if (String(rows[i][column] || '').trim() === expected) {
        return i + 1;
      }
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

function indexQsManagerV2Headers_(headers) {
  const columns = {};
  headers.forEach((header, index) => {
    const normalized = normalizeQsManagerV2Key_(header);
    if (normalized) columns[normalized] = index;
  });
  return columns;
}

function findQsManagerV2Header_(rows, requiredHeaders) {
  const scanLimit = Math.min(rows.length, 10);
  for (let index = 0; index < scanLimit; index += 1) {
    const columns = indexQsManagerV2Headers_(rows[index]);
    const hasRequired = requiredHeaders.every((header) => (
      columns[normalizeQsManagerV2Key_(header)] != null
    ));
    if (hasRequired) {
      return {
        index,
        columns,
      };
    }
  }
  return null;
}

function valueQsManagerV2_(row, columns, candidateHeaders, fallbackIndex) {
  for (let index = 0; index < candidateHeaders.length; index += 1) {
    const column = columns[normalizeQsManagerV2Key_(candidateHeaders[index])];
    if (column != null) return row[column];
  }
  return fallbackIndex == null ? '' : row[fallbackIndex];
}

function stringQsManagerV2Value_(value) {
  if (value == null) return '';
  if (value instanceof Date && !isNaN(value.getTime())) {
    return Utilities.formatDate(value, 'America/Santiago', 'yyyy-MM-dd');
  }
  return String(value).trim();
}

function numberOrBlankQsManagerV2_(value) {
  if (value === '' || value == null) return '';
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : '';
}

function integerQsManagerV2Value_(value) {
  if (value === '' || value == null) return 0;
  if (typeof value === 'number') return Math.round(value);
  const normalized = String(value)
    .replace(/[^\d,-]/g, '')
    .replace(/\./g, '')
    .replace(',', '.');
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? Math.round(parsed) : 0;
}

function booleanQsManagerV2Value_(value, defaultValue) {
  if (value === '' || value == null) return defaultValue;
  if (value === false) return false;
  const normalized = normalizeQsManagerV2Key_(value);
  if (['false', 'falso', 'no', '0', 'inactivo', 'inactiva'].includes(normalized)) return false;
  return true;
}

function positiveLimitQsManagerV2_(value, defaultValue) {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed <= 0) return defaultValue;
  return Math.min(parsed, 1000);
}

function latestQsManagerV2Date_(current, candidate) {
  const currentKey = dateKeyQsManagerV2_(current);
  const candidateKey = dateKeyQsManagerV2_(candidate);
  if (!candidateKey) return current;
  if (!currentKey || candidateKey > currentKey) return stringQsManagerV2Value_(candidate);
  return current;
}

function dateKeyQsManagerV2_(value) {
  if (value instanceof Date && !isNaN(value.getTime())) {
    return Utilities.formatDate(value, 'America/Santiago', 'yyyy-MM-dd');
  }
  return String(value || '').trim();
}

function normalizeQsManagerV2Key_(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ');
}


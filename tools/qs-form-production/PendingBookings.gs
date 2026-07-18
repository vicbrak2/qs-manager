/**
 * Pending booking management for the QS production form.
 * Add this file to the standalone Apps Script project that owns Cotizador.html.
 */

const QS_PENDING_CONFIG = Object.freeze({
  agendaSpreadsheetId: '1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4',
  bitacoraSpreadsheetId: '1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE',
  bitacoraSheetName: 'Bitácora QS — Servicios',
  calendarId: 'qamilunaservices@qamilunastudio.com',
  timezone: 'America/Santiago',
  maxResults: 200,
  monthSheets: [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ],
  terminalStates: ['terminado', 'finalizado', 'realizado', 'completado'],
  cancelledStates: ['cancelado', 'cancelada', 'eliminado', 'eliminada']
});

function qsListPendingBookings() {
  const agenda = SpreadsheetApp.openById(QS_PENDING_CONFIG.agendaSpreadsheetId);
  const now = new Date();
  const rows = [];
  const bitacoraStates = qsPendingLoadBitacoraStates_();

  QS_PENDING_CONFIG.monthSheets.forEach(function (sheetName) {
    const sheet = agenda.getSheetByName(sheetName);
    if (!sheet || sheet.getLastRow() < 2) return;

    const values = sheet.getDataRange().getValues();
    const columns = qsPendingHeaderMap_(values[0]);

    for (let index = 1; index < values.length; index += 1) {
      const row = values[index];
      const date = qsPendingDateTime_(
        qsPendingValue_(row, columns, 'fecha'),
        qsPendingValue_(row, columns, 'hora')
      );
      const eventState = qsPendingText_(qsPendingValue_(row, columns, 'estado evento'));
      const rowNumber = index + 1;
      const eventId = qsPendingText_(qsPendingValue_(row, columns, 'id evento'));
      const reference = 'Agenda: ' + sheetName + '!' + rowNumber;
      const bitacoraState = bitacoraStates.byReference[reference]
        || bitacoraStates.byEventId[eventId]
        || '';
      const serviceState = qsPendingText_(
        qsPendingValue_(row, columns, 'estado servicio') || bitacoraState
      );
      const combinedState = qsPendingNormalize_(eventState + ' ' + serviceState);
      const hasContent = Boolean(
        qsPendingText_(qsPendingValue_(row, columns, 'servicio')) ||
        qsPendingText_(qsPendingValue_(row, columns, 'clienta'))
      );

      if (!hasContent || qsPendingHasState_(combinedState, QS_PENDING_CONFIG.cancelledStates)) continue;

      const isFuture = date && date.getTime() >= now.getTime();
      if (!isFuture) continue;

      const tokenPayload = [sheetName, rowNumber, eventId, reference].join('|');

      rows.push({
        token: qsPendingSignToken_(tokenPayload),
        sheet: sheetName,
        row: rowNumber,
        reference: reference,
        date: date ? Utilities.formatDate(date, QS_PENDING_CONFIG.timezone, 'yyyy-MM-dd') : '',
        dateLabel: date ? Utilities.formatDate(date, QS_PENDING_CONFIG.timezone, 'dd/MM/yyyy') : 'Sin fecha',
        time: date ? Utilities.formatDate(date, QS_PENDING_CONFIG.timezone, 'HH:mm') : '',
        service: qsPendingText_(qsPendingValue_(row, columns, 'servicio')),
        customer: qsPendingText_(qsPendingValue_(row, columns, 'clienta')),
        staff: qsPendingText_(qsPendingValue_(row, columns, 'encargada')),
        status: serviceState || eventState || 'Pendiente',
        total: qsPendingNumber_(qsPendingValue_(row, columns, 'total servicio')),
        balance: qsPendingNumber_(qsPendingValue_(row, columns, 'total por pagar')),
        isFuture: Boolean(isFuture)
      });
    }
  });

  rows.sort(function (left, right) {
    const leftKey = (left.date || '9999-12-31') + 'T' + (left.time || '23:59');
    const rightKey = (right.date || '9999-12-31') + 'T' + (right.time || '23:59');
    return leftKey.localeCompare(rightKey) || left.reference.localeCompare(right.reference);
  });

  return {
    generatedAt: Utilities.formatDate(now, QS_PENDING_CONFIG.timezone, "yyyy-MM-dd'T'HH:mm:ssXXX"),
    count: Math.min(rows.length, QS_PENDING_CONFIG.maxResults),
    truncated: rows.length > QS_PENDING_CONFIG.maxResults,
    bookings: rows.slice(0, QS_PENDING_CONFIG.maxResults)
  };
}

function qsPendingLoadBitacoraStates_() {
  const result = { byReference: {}, byEventId: {} };
  const spreadsheet = SpreadsheetApp.openById(QS_PENDING_CONFIG.bitacoraSpreadsheetId);
  const sheet = spreadsheet.getSheetByName(QS_PENDING_CONFIG.bitacoraSheetName);
  if (!sheet || sheet.getLastRow() < 2) return result;

  const values = sheet.getDataRange().getValues();
  const columns = qsPendingHeaderMap_(values[0]);
  for (let index = 1; index < values.length; index += 1) {
    const row = values[index];
    const reference = qsPendingText_(qsPendingValue_(row, columns, 'referencia agenda'));
    const eventId = qsPendingText_(qsPendingValue_(row, columns, 'id calendar'));
    const state = qsPendingText_(qsPendingValue_(row, columns, 'estado servicio'));
    if (reference) result.byReference[reference] = state;
    if (eventId) result.byEventId[eventId] = state;
  }
  return result;
}

function qsCancelPendingBooking(token, reason) {
  const cleanReason = qsPendingText_(reason);
  if (cleanReason.length < 5 || cleanReason.length > 240) {
    throw new Error('Indica un motivo de cancelación entre 5 y 240 caracteres.');
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    const payload = qsPendingVerifyToken_(token);
    const parts = payload.split('|');
    const sheetName = parts[0];
    const rowNumber = Number(parts[1]);
    const tokenEventId = parts[2];
    const reference = parts[3];

    if (!QS_PENDING_CONFIG.monthSheets.includes(sheetName) || !Number.isInteger(rowNumber) || rowNumber < 2) {
      throw new Error('La referencia de la reserva no es válida.');
    }

    const agenda = SpreadsheetApp.openById(QS_PENDING_CONFIG.agendaSpreadsheetId);
    const sheet = agenda.getSheetByName(sheetName);
    if (!sheet || rowNumber > sheet.getLastRow()) throw new Error('La reserva ya no existe en Agenda.');

    const width = sheet.getLastColumn();
    const columns = qsPendingHeaderMap_(sheet.getRange(1, 1, 1, width).getValues()[0]);
    const rowRange = sheet.getRange(rowNumber, 1, 1, width);
    const row = rowRange.getValues()[0];
    const currentEventId = qsPendingText_(qsPendingValue_(row, columns, 'id evento'));
    const state = qsPendingNormalize_(
      qsPendingText_(qsPendingValue_(row, columns, 'estado evento')) + ' ' +
      qsPendingText_(qsPendingValue_(row, columns, 'estado servicio'))
    );

    if (qsPendingHasState_(state, QS_PENDING_CONFIG.cancelledStates)) {
      return { ok: true, alreadyCancelled: true, reference: reference };
    }
    if (tokenEventId && currentEventId && tokenEventId !== currentEventId) {
      throw new Error('La reserva cambió desde que se cargó la lista. Actualiza e intenta nuevamente.');
    }

    const bitacoraContext = qsPendingFindBitacoraRow_(reference, currentEventId);
    const stamp = Utilities.formatDate(new Date(), QS_PENDING_CONFIG.timezone, "yyyy-MM-dd'T'HH:mm:ssXXX");
    const auditNote = '[CANCELADO DESDE COTIZADOR ' + stamp + '] ' + cleanReason;

    // Persist a recoverable state before touching Calendar. If deletion fails,
    // the next attempt can resume without presenting the cancellation as complete.
    qsPendingSetCell_(sheet, rowNumber, columns, 'estado evento', 'CANCELACION_PENDIENTE - FORMULARIO');
    qsPendingSetCell_(sheet, rowNumber, columns, 'accion', 'CANCELAR');

    // Agenda may not store the Calendar id; Bitácora ("ID Calendar") is the
    // authoritative fallback. Without this, deletion is skipped silently.
    const effectiveEventId = currentEventId || (bitacoraContext && bitacoraContext.eventId) || '';
    let calendarDeleted = false;
    if (effectiveEventId) {
      const calendar = CalendarApp.getCalendarById(QS_PENDING_CONFIG.calendarId);
      if (!calendar) throw new Error('No se encontró el calendario configurado.');
      const event = qsPendingGetEvent_(calendar, effectiveEventId);
      if (event) {
        event.deleteEvent();
        calendarDeleted = true;
      }
    }

    qsPendingSetCell_(sheet, rowNumber, columns, 'estado evento', 'CANCELADO - FORMULARIO');
    qsPendingSetCell_(sheet, rowNumber, columns, 'estado servicio', 'Cancelado');
    qsPendingAppendNote_(sheet, rowNumber, columns, 'estado evento', auditNote);

    if (bitacoraContext) {
      qsPendingSetCell_(bitacoraContext.sheet, bitacoraContext.row, bitacoraContext.columns, 'estado servicio', 'Cancelado');
      qsPendingSetCell_(bitacoraContext.sheet, bitacoraContext.row, bitacoraContext.columns, 'estado contrato', 'Cancelado');
      qsPendingAppendText_(bitacoraContext.sheet, bitacoraContext.row, bitacoraContext.columns, 'observaciones', auditNote);
    }

    return {
      ok: true,
      alreadyCancelled: false,
      reference: reference,
      calendarDeleted: calendarDeleted,
      bitacoraUpdated: Boolean(bitacoraContext),
      message: calendarDeleted || !effectiveEventId
        ? 'Reserva cancelada. QS Manager V2 la reflejará en la próxima sincronización.'
        : 'Reserva cancelada en Agenda y Bitácora, pero el evento de Calendar no pudo eliminarse. Revísalo manualmente.'
    };
  } finally {
    lock.releaseLock();
  }
}

function qsPendingFindBitacoraRow_(reference, eventId) {
  const spreadsheet = SpreadsheetApp.openById(QS_PENDING_CONFIG.bitacoraSpreadsheetId);
  const sheet = spreadsheet.getSheetByName(QS_PENDING_CONFIG.bitacoraSheetName);
  if (!sheet || sheet.getLastRow() < 2) return null;

  const values = sheet.getDataRange().getValues();
  const columns = qsPendingHeaderMap_(values[0]);
  for (let index = 1; index < values.length; index += 1) {
    const row = values[index];
    const rowReference = qsPendingText_(qsPendingValue_(row, columns, 'referencia agenda'));
    const rowEventId = qsPendingText_(qsPendingValue_(row, columns, 'id calendar'));
    if ((reference && rowReference === reference) || (eventId && rowEventId === eventId)) {
      return { sheet: sheet, row: index + 1, columns: columns, eventId: rowEventId };
    }
  }
  return null;
}

function qsPendingGetEvent_(calendar, eventId) {
  // Ids may be stored with or without the "@google.com" suffix depending on
  // which API wrote them; CalendarApp only resolves its own format.
  const candidates = [eventId];
  if (eventId.indexOf('@') === -1) candidates.push(eventId + '@google.com');
  else candidates.push(eventId.split('@')[0]);
  for (let index = 0; index < candidates.length; index += 1) {
    try {
      const event = calendar.getEventById(candidates[index]);
      if (event) return event;
    } catch (error) {
      // Ignore malformed-id lookups and try the next candidate.
    }
  }
  return null;
}

function qsPendingSignToken_(payload) {
  const secret = qsPendingSecret_();
  const signature = Utilities.base64EncodeWebSafe(
    Utilities.computeHmacSha256Signature(payload, secret)
  ).replace(/=+$/, '');
  return Utilities.base64EncodeWebSafe(payload).replace(/=+$/, '') + '.' + signature;
}

function qsPendingVerifyToken_(token) {
  const parts = qsPendingText_(token).split('.');
  if (parts.length !== 2) throw new Error('Token de reserva inválido.');
  const payload = Utilities.newBlob(Utilities.base64DecodeWebSafe(parts[0])).getDataAsString();
  const expected = qsPendingSignToken_(payload).split('.')[1];
  if (parts[1] !== expected) throw new Error('Token de reserva inválido.');
  return payload;
}

function qsPendingSecret_() {
  const properties = PropertiesService.getScriptProperties();
  let secret = properties.getProperty('QS_PENDING_TOKEN_SECRET');
  if (!secret) {
    secret = Utilities.getUuid() + Utilities.getUuid();
    properties.setProperty('QS_PENDING_TOKEN_SECRET', secret);
  }
  return secret;
}

function qsPendingDateTime_(dateValue, timeValue) {
  if (!dateValue) return null;
  const dateText = dateValue instanceof Date
    ? Utilities.formatDate(dateValue, QS_PENDING_CONFIG.timezone, 'yyyy-MM-dd')
    : qsPendingText_(dateValue);
  const timeText = timeValue instanceof Date
    ? Utilities.formatDate(timeValue, QS_PENDING_CONFIG.timezone, 'HH:mm')
    : qsPendingText_(timeValue);
  const dateMatch = dateText.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/) || dateText.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if (!dateMatch) return null;

  const isoDate = dateText.includes('-')
    ? [dateMatch[1], dateMatch[2], dateMatch[3]].join('-')
    : [dateMatch[3], dateMatch[2], dateMatch[1]].join('-');
  const timeMatch = timeText.match(/(\d{1,2}):(\d{2})/);
  const hour = timeMatch ? Number(timeMatch[1]) : 0;
  const minute = timeMatch ? Number(timeMatch[2]) : 0;
  const dateParts = isoDate.split('-').map(Number);
  return new Date(dateParts[0], dateParts[1] - 1, dateParts[2], hour, minute, 0, 0);
}

function qsPendingHeaderMap_(headers) {
  const map = {};
  headers.forEach(function (header, index) {
    const key = qsPendingNormalize_(header);
    if (key && map[key] === undefined) map[key] = index;
  });
  return map;
}

function qsPendingValue_(row, columns, header) {
  const index = columns[qsPendingNormalize_(header)];
  return index === undefined ? '' : row[index];
}

function qsPendingSetCell_(sheet, row, columns, header, value) {
  const index = columns[qsPendingNormalize_(header)];
  if (index !== undefined) sheet.getRange(row, index + 1).setValue(value);
}

function qsPendingAppendText_(sheet, row, columns, header, text) {
  const index = columns[qsPendingNormalize_(header)];
  if (index === undefined) return;
  const cell = sheet.getRange(row, index + 1);
  const current = qsPendingText_(cell.getValue());
  cell.setValue(current ? current + '\n' + text : text);
}

function qsPendingAppendNote_(sheet, row, columns, header, text) {
  const index = columns[qsPendingNormalize_(header)];
  if (index === undefined) return;
  const cell = sheet.getRange(row, index + 1);
  const current = qsPendingText_(cell.getNote());
  cell.setNote(current ? current + '\n' + text : text);
}

function qsPendingHasState_(state, candidates) {
  return candidates.some(function (candidate) { return state.includes(candidate); });
}

function qsPendingNormalize_(value) {
  return qsPendingText_(value)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ');
}

function qsPendingText_(value) {
  return String(value === null || value === undefined ? '' : value).trim();
}

function qsPendingNumber_(value) {
  if (typeof value === 'number' && isFinite(value)) return Math.round(value);
  const normalized = qsPendingText_(value).replace(/[^\d-]/g, '');
  const parsed = Number(normalized);
  return isFinite(parsed) ? parsed : 0;
}

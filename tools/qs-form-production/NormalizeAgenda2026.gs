function qsNormalizeAgendaAndBitacora2026() {
  const agendaId = '1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4';
  const bitacoraId = '1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE';
  const months = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];
  const cutoff = new Date(2026, 6, 17, 23, 59, 59);
  const yearStart = new Date(2026, 0, 1);
  const summary = { agendaPast: 0, agendaFuture: 0, agendaDeleted: 0, bitacoraPast: 0, bitacoraFuture: 0, bitacoraDeleted: 0 };

  const agenda = SpreadsheetApp.openById(agendaId);
  months.forEach(function (name) {
    const sheet = agenda.getSheetByName(name);
    if (!sheet || sheet.getLastRow() < 2) return;
    const values = sheet.getDataRange().getValues();
    const headers = qsMaintenanceHeaders_(values[0]);

    for (let index = values.length - 1; index >= 1; index -= 1) {
      const row = values[index];
      const service = qsMaintenanceText_(row[headers.servicio]);
      const customer = qsMaintenanceText_(row[headers.clienta]);
      const address = qsMaintenanceText_(row[headers.direccion]);
      const eventStatus = qsMaintenanceText_(row[headers.estadoevento]);
      if (!service || !customer) continue;

      if (qsMaintenanceIsTest_(customer, address) || /cancel/i.test(eventStatus)) {
        sheet.deleteRow(index + 1);
        summary.agendaDeleted += 1;
        continue;
      }

      const date = qsMaintenanceDate_(row[headers.fecha]);
      if (!date) continue;
      const isPast = date.getTime() <= cutoff.getTime();
      const deposit = qsMaintenanceNumber_(row[headers.abono]);
      const balance = qsMaintenanceNumber_(row[headers.totalporpagar]);
      sheet.getRange(index + 1, headers.estadoevento + 1).setValue(isPast ? 'TERMINADO' : 'CONFIRMADO');
      sheet.getRange(index + 1, headers.estadopago + 1).setValue(
        isPast ? 'Pagado' : (balance <= 0 ? 'Pagado' : (deposit > 0 ? 'Parcial' : 'Pendiente'))
      );
      isPast ? summary.agendaPast += 1 : summary.agendaFuture += 1;
    }
  });

  const bitacora = SpreadsheetApp.openById(bitacoraId).getSheetByName('Bitácora QS — Servicios');
  if (!bitacora) throw new Error('No se encontró la hoja Bitácora QS — Servicios.');
  const bitacoraValues = bitacora.getDataRange().getValues();
  const bitacoraHeaders = qsMaintenanceHeaders_(bitacoraValues[0]);

  for (let index = bitacoraValues.length - 1; index >= 1; index -= 1) {
    const row = bitacoraValues[index];
    const service = qsMaintenanceText_(row[bitacoraHeaders.servicio]);
    const customer = qsMaintenanceText_(row[bitacoraHeaders.clienta]);
    const address = qsMaintenanceText_(row[bitacoraHeaders.direccion]);
    const serviceStatus = qsMaintenanceText_(row[bitacoraHeaders.estadoservicio]);
    if (!service || !customer) continue;

    if (qsMaintenanceIsTest_(customer, address) || /cancel/i.test(serviceStatus)) {
      bitacora.deleteRow(index + 1);
      summary.bitacoraDeleted += 1;
      continue;
    }

    const date = qsMaintenanceDate_(row[bitacoraHeaders.fecha]);
    if (!date || date.getTime() < yearStart.getTime()) continue;
    const isPast = date.getTime() <= cutoff.getTime();
    const deposit = qsMaintenanceNumber_(row[bitacoraHeaders.abono]);
    const balance = qsMaintenanceNumber_(row[bitacoraHeaders.saldo]);
    bitacora.getRange(index + 1, bitacoraHeaders.estadopago + 1).setValue(
      isPast ? 'Pagado' : (balance <= 0 ? 'Pagado' : (deposit > 0 ? 'Parcial' : 'Pendiente'))
    );
    bitacora.getRange(index + 1, bitacoraHeaders.estadoservicio + 1).setValue(isPast ? 'Terminado' : 'Confirmado');
    bitacora.getRange(index + 1, bitacoraHeaders.estadocontrato + 1).setValue('Confirmado');
    isPast ? summary.bitacoraPast += 1 : summary.bitacoraFuture += 1;
  }

  Logger.log(JSON.stringify(summary));
  return summary;
}

function qsMaintenanceHeaders_(headers) {
  return headers.reduce(function (result, header, index) {
    const key = String(header || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');
    if (key) result[key] = index;
    return result;
  }, {});
}

function qsMaintenanceText_(value) {
  return String(value == null ? '' : value).trim();
}

function qsMaintenanceNumber_(value) {
  if (typeof value === 'number') return value;
  const parsed = Number(String(value || '').replace(/[^0-9-]/g, ''));
  return Number.isFinite(parsed) ? parsed : 0;
}

function qsMaintenanceDate_(value) {
  if (value instanceof Date && !Number.isNaN(value.getTime())) return value;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function qsMaintenanceIsTest_(customer, address) {
  const customerKey = qsMaintenanceText_(customer).toLowerCase();
  const text = (customerKey + ' ' + qsMaintenanceText_(address).toLowerCase());
  return customerKey === 'prueba' || /qa e2e|prueba qs v2|no cliente|test qa|registro automatico de prueba/.test(
    text.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  );
}

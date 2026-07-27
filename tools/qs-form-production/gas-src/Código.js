/**
 * Formulario QS -> Agenda 2026
 *
 * Instalar en el proyecto Apps Script vinculado a la planilla Agenda.
 * Ejecutar una sola vez: qsCrearFormularioRegistro()
 *
 * El formulario escribe en la pestaña mensual correspondiente, usando los
 * encabezados actuales de Agenda 2026. Las pruebas de novia quedan en la
 * misma fila del servicio principal: Fecha Prueba, Hora Prueba, Estado Prueba.
 */

const QS_FORM_CONFIG = {
  spreadsheetId: '1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4',
  bitacoraSpreadsheetId: '1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE',
  bitacoraSheet: 'Bitácora QS — Servicios',
  formTitle: 'Registro de Servicios QS',
  formId: '10dt5Y8toT8YuPuQWugYCxlsD2_xnjI2tpWvGZX3aHc4',
  configSheet: 'Config',
  serviceRangeA1: 'E2:E120',
  encargadasRangeA1: 'F2:F40',
  calendarId: 'qamilunaservices@qamilunastudio.com',
  defaultCalendarGuest: '',
  inviteAssignedStaff: true,
  assignedStaffEmails: {
    paz: 'vi.espectral@gmail.com',
    mou: 'mymarchantc@gmail.com',
    cami: 'cami.verdejo@gmail.com'
  },
  groupServicesRangeA1: 'G2:G40',
  additionalServicesRangeA1: 'H2:H60',
  proofStatusesRangeA1: 'I2:I20',
  monthSheets: [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ]
};

function qsCrearFormularioRegistro() {
  const ss = qsGetSpreadsheet_();
  const cfg = ss.getSheetByName(QS_FORM_CONFIG.configSheet);
  if (!cfg) {
    throw new Error(
      'No existe la hoja Config en "' + ss.getName() + '". Hojas disponibles: ' +
      ss.getSheets().map(sheet => sheet.getName()).join(', ')
    );
  }

  const servicios = qsReadList_(cfg, QS_FORM_CONFIG.serviceRangeA1);
  const encargadas = qsReadList_(cfg, QS_FORM_CONFIG.encargadasRangeA1);
  const adicionales = qsReadList_(cfg, QS_FORM_CONFIG.additionalServicesRangeA1);

  if (!servicios.length) throw new Error('No hay servicios en Config!' + QS_FORM_CONFIG.serviceRangeA1);
  if (!encargadas.length) throw new Error('No hay encargadas en Config!' + QS_FORM_CONFIG.encargadasRangeA1);

  const form = FormApp.create(QS_FORM_CONFIG.formTitle);
  form.setDescription('Formulario interno para registrar servicios QS en la Agenda.');
  form.setDestination(FormApp.DestinationType.SPREADSHEET, ss.getId());
  form.setCollectEmail(false);

  form.addSectionHeaderItem().setTitle('Información del cliente');
  form.addTextItem().setTitle('Nombre de la Clienta').setRequired(true);
  form.addTextItem().setTitle('Teléfono').setRequired(true);
  form.addTextItem().setTitle('Dirección').setRequired(true);
  form.addTextItem().setTitle('Comuna').setRequired(true);

  form.addSectionHeaderItem().setTitle('Detalles del servicio');
  form.addListItem().setTitle('Encargada').setChoiceValues(encargadas).setRequired(true);
  form.addDateItem().setTitle('Fecha del Servicio').setRequired(true);
  form.addTextItem()
    .setTitle('Hora del Servicio')
    .setHelpText('Formato recomendado: HH:MM, por ejemplo 09:30')
    .setRequired(true);
  form.addListItem().setTitle('Servicio Principal').setChoiceValues(servicios).setRequired(true);
  form.addTextItem()
    .setTitle('Cantidad')
    .setHelpText('Número entero. Usar 1 para servicios individuales.')
    .setRequired(true);

  form.addSectionHeaderItem().setTitle('Prueba de novia');
  form.addMultipleChoiceItem().setTitle('¿Requiere Prueba?').setChoiceValues(['Sí', 'No']).setRequired(true);
  form.addDateItem().setTitle('Fecha de la Prueba').setRequired(false);
  form.addTextItem()
    .setTitle('Hora de la Prueba')
    .setHelpText('Formato recomendado: HH:MM. Dejar vacío si no aplica.')
    .setRequired(false);

  form.addSectionHeaderItem().setTitle('Valores y pagos');
  form.addTextItem().setTitle('Traslado').setHelpText('Monto en pesos. Usar 0 si no aplica.').setRequired(true);
  form.addTextItem().setTitle('Abono').setHelpText('Monto en pesos. Usar 0 si no aplica.').setRequired(true);
  form.addDateItem().setTitle('Fecha de Abono').setRequired(false);
  form.addTextItem().setTitle('Valor Servicio').setHelpText('Monto neto del servicio, sin traslado.').setRequired(true);

  form.addSectionHeaderItem().setTitle('Servicios adicionales');
  form.addMultipleChoiceItem()
    .setTitle('¿Agregar servicios adicionales para acompañantes?')
    .setChoiceValues(['Sí', 'No'])
    .setRequired(true);
  form.addCheckboxItem()
    .setTitle('Servicios Adicionales')
    .setChoiceValues(adicionales.length ? adicionales : ['Social Maquillaje', 'Social Peinado'])
    .setRequired(false);
  form.addTextItem().setTitle('Cantidad Acompañantes').setHelpText('Número entero.').setRequired(false);
  form.addTextItem().setTitle('Valor de Acompañantes').setHelpText('Monto total de acompañantes.').setRequired(false);

  PropertiesService.getScriptProperties().setProperties({
    QS_FORM_ID: form.getId(),
    QS_FORM_EDIT_URL: form.getEditUrl(),
    QS_FORM_PUBLIC_URL: form.getPublishedUrl()
  });

  ScriptApp.newTrigger('qsProcesarFormulario')
    .forForm(form)
    .onFormSubmit()
    .create();

  Logger.log('Formulario creado: ' + form.getPublishedUrl());
  Logger.log('Editor: ' + form.getEditUrl());
  return {
    publicUrl: form.getPublishedUrl(),
    editUrl: form.getEditUrl()
  };
}

function qsProcesarFormulario(e) {
  const respuesta = qsNormalizeTypedResponse_(qsMapFormResponse_(e));
  const ss = qsGetSpreadsheet_();

  const fechaServicio = qsRequiredDate_(respuesta['Fecha del Servicio'], 'Fecha del Servicio');
  const mes = QS_FORM_CONFIG.monthSheets[fechaServicio.getMonth()];
  const sheet = ss.getSheetByName(mes);
  if (!sheet) throw new Error('No existe la hoja mensual: ' + mes);

  const headers = qsGetHeaderMap_(sheet);
  const servicioPrincipal = qsRequiredText_(respuesta['Servicio Principal'], 'Servicio Principal');
  const cantidad = qsParseInteger_(respuesta['Cantidad'], 1);
  const rowData = qsBuildAgendaRow_(respuesta, servicioPrincipal, cantidad, fechaServicio);

  const principalRow = qsAppendAgendaRow_(sheet, headers, rowData);
  qsSyncAgendaRow_(sheet, headers, principalRow);
  qsAppendAdditionalServices_(sheet, headers, respuesta, principalRow, fechaServicio);
}

function qsActualizarFormularioRegistro() {
  const ss = qsGetSpreadsheet_();
  const cfg = ss.getSheetByName(QS_FORM_CONFIG.configSheet);
  if (!cfg) throw new Error('No existe la hoja Config.');

  const servicios = qsReadList_(cfg, QS_FORM_CONFIG.serviceRangeA1);
  const encargadas = qsReadList_(cfg, QS_FORM_CONFIG.encargadasRangeA1);
  const adicionales = qsReadList_(cfg, QS_FORM_CONFIG.additionalServicesRangeA1);
  if (!servicios.length) throw new Error('No hay servicios activos configurados.');
  if (!encargadas.length) throw new Error('No hay encargadas configuradas.');

  const form = FormApp.openById(QS_FORM_CONFIG.formId);
  const byTitle = {};
  form.getItems().forEach(item => {
    const title = item.getTitle();
    if (title) byTitle[title] = item;
  });

  byTitle['Servicio Principal'].asListItem().setChoiceValues(servicios);
  byTitle['Encargada'].asListItem().setChoiceValues(encargadas);
  byTitle['Servicios Adicionales'].asCheckboxItem().setChoiceValues(adicionales);

  const moneyValidation = FormApp.createTextValidation()
    .requireNumberGreaterThanOrEqualTo(0)
    .setHelpText('Ingresa un monto numérico igual o mayor que 0.')
    .build();
  const quantityValidation = FormApp.createTextValidation()
    .requireWholeNumber()
    .requireNumberGreaterThanOrEqualTo(1)
    .setHelpText('Ingresa un número entero igual o mayor que 1.')
    .build();
  const timeValidation = FormApp.createTextValidation()
    .requireTextMatchesPattern('^([01]?[0-9]|2[0-3]):[0-5][0-9]$')
    .setHelpText('Usa formato HH:MM, por ejemplo 09:30.')
    .build();
  const phoneValidation = FormApp.createTextValidation()
    .requireTextMatchesPattern('^[+0-9][0-9\\s-]{7,20}$')
    .setHelpText('Ingresa un teléfono válido, por ejemplo +56912345678.')
    .build();

  byTitle['Cantidad'].asTextItem().setValidation(quantityValidation);
  byTitle['Cantidad Acompañantes'].asTextItem().setValidation(quantityValidation);
  byTitle['Traslado'].asTextItem().setValidation(moneyValidation);
  byTitle['Abono'].asTextItem().setValidation(moneyValidation);
  byTitle['Valor Servicio'].asTextItem().setValidation(moneyValidation);
  byTitle['Valor de Acompañantes'].asTextItem().setValidation(moneyValidation);
  byTitle['Hora del Servicio'].asTextItem().setValidation(timeValidation);
  byTitle['Hora de la Prueba'].asTextItem().setValidation(timeValidation);
  byTitle['Teléfono'].asTextItem().setValidation(phoneValidation);

  form.setDescription(
    'Formulario interno para registrar reservas QS en Agenda 2026. ' +
    'Cada envío crea una fila individual, incluidos los talleres.'
  );

  return {
    services: servicios.length,
    additionalServices: adicionales.length,
    publicUrl: form.getPublishedUrl()
  };
}

function qsBuildAgendaRow_(respuesta, servicio, cantidad, fechaServicio) {
  const requierePrueba = String(respuesta['¿Requiere Prueba?'] || '').toLowerCase().indexOf('s') === 0;
  const fechaPrueba = qsOptionalDate_(respuesta['Fecha de la Prueba']);
  const horaPrueba = qsNormalizeTime_(respuesta['Hora de la Prueba']);
  const estadoPrueba = requierePrueba ? 'Agendada' : 'No aplica';
  const lugarPrueba = requierePrueba ? String(respuesta['Lugar de la Prueba'] || '').trim() : '';
  const direccionPrueba = requierePrueba ? String(respuesta['Dirección de la Prueba'] || '').trim() : '';
  const abono = qsParseMoney_(respuesta['Abono']);
  const estadoPago = abono > 0 ? 'Parcial' : 'Pendiente';
  const tipo = String(respuesta['Tipo de registro'] || 'Servicio regular');

  return {
    'Encargada': qsRequiredText_(respuesta['Encargada'], 'Encargada'),
    'Día': tipo === 'Taller' ? 'Taller' : (tipo === 'Glitter Bar' ? 'Evento' : (tipo === 'Novia' ? 'Novia' : 'Servicio')),
    'Fecha': fechaServicio,
    'Hora': qsNormalizeTime_(respuesta['Hora del Servicio']),
    'Servicio': servicio,
    'Cantidad': cantidad,
    'Clienta': qsRequiredText_(respuesta['Nombre de la Clienta'], 'Nombre de la Clienta'),
    'Teléfono': qsRequiredText_(respuesta['Teléfono'], 'Teléfono'),
    'Dirección': qsRequiredText_(respuesta['Dirección'], 'Dirección'),
    'Comuna': qsRequiredText_(respuesta['Comuna'], 'Comuna'),
    'Fecha Prueba': requierePrueba ? fechaPrueba : '',
    'Hora Prueba': requierePrueba ? horaPrueba : '',
    'Lugar Prueba': lugarPrueba,
    'Lugar de la Prueba': lugarPrueba,
    'Dirección Prueba': direccionPrueba,
    'Dirección de la Prueba': direccionPrueba,
    'Estado Prueba': estadoPrueba,
    'Traslado': qsParseMoney_(respuesta['Traslado']),
    'Abono': abono,
    'Fecha Abono': qsOptionalDate_(respuesta['Fecha de Abono']),
    'Valor Servicio': qsParseMoney_(respuesta['Valor Servicio']),
    'Observaciones': String(respuesta['Observaciones'] || '').trim(),
    'Acción': 'CREAR',
    'Estado Pago': estadoPago,
    'Estado Servicio': 'Agendado'
  };
}

function qsAppendAdditionalServices_(sheet, headers, respuesta, principalRow, fechaServicio) {
  const agregar = String(respuesta['¿Agregar servicios adicionales para acompañantes?'] || '').toLowerCase().indexOf('s') === 0;
  if (!agregar) return;

  const servicios = qsAsArray_(respuesta['Servicios Adicionales']).filter(Boolean);
  if (!servicios.length) return;

  const cantidad = qsParseInteger_(respuesta['Cantidad Acompañantes'], 1);
  const valorTotal = qsParseMoney_(respuesta['Valor de Acompañantes']);
  const valorPorFila = servicios.length ? Math.round(valorTotal / servicios.length) : 0;

  servicios.forEach((servicio, index) => {
    const rowData = qsBuildAgendaRow_(respuesta, servicio, cantidad, fechaServicio);
    rowData['Valor Servicio'] = valorPorFila;
    rowData['Traslado'] = 0;
    rowData['Abono'] = index === 0 ? 0 : 0;
    rowData['Fecha Prueba'] = '';
    rowData['Hora Prueba'] = '';
    rowData['Estado Prueba'] = 'No aplica';
    rowData['Acción'] = 'CREAR';
    const row = qsInsertAgendaRowAfter_(sheet, headers, principalRow + index, rowData);
    qsSyncAgendaRow_(sheet, headers, row);
  });
}

function qsAppendAgendaRow_(sheet, headers, rowData) {
  const row = qsFindFirstEmptyAgendaRow_(sheet, headers);
  qsPrepareAgendaRow_(sheet, row);
  qsWriteAgendaRow_(sheet, headers, row, rowData);
  return row;
}

function qsInsertAgendaRowAfter_(sheet, headers, afterRow, rowData) {
  sheet.insertRowAfter(afterRow);
  const row = afterRow + 1;
  qsPrepareAgendaRow_(sheet, row);
  qsWriteAgendaRow_(sheet, headers, row, rowData);
  return row;
}

function qsPrepareAgendaRow_(sheet, row) {
  if (row <= 2) return;
  [
    [1, 17],
    [20, 1],
    [23, 1]
  ].forEach(([col, width]) => {
    const source = sheet.getRange(row - 1, col, 1, width);
    const target = sheet.getRange(row, col, 1, width);
    source.copyTo(target, SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
    source.copyTo(target, SpreadsheetApp.CopyPasteType.PASTE_DATA_VALIDATION, false);
    target.clearContent();
  });
}

function qsWriteAgendaRow_(sheet, headers, row, rowData) {
  Object.keys(rowData).forEach(name => {
    const col = headers[qsNormalizeHeader_(name)];
    if (!col) return;
    sheet.getRange(row, col).setValue(rowData[name]);
  });

  // Total servicio, Total por pagar, Estado Evento e ID Evento pueden estar
  // protegidos en Agenda. No se escriben desde este proyecto auxiliar.
}

function qsSyncAgendaRow_(sheet, headers, row) {
  SpreadsheetApp.flush();
  const data = qsReadAgendaRow_(sheet, headers, row);
  if (!data.Servicio || !data.Fecha) return;

  const ref = `Agenda: ${sheet.getName()}!${row}`;
  let eventId = data['ID Evento'] || qsGetBitacoraEventIdByReference_(ref);
  if (!eventId) {
    eventId = qsCreateCalendarEvent_(data);
  }
  qsUpsertBitacora_(sheet, row, Object.assign({}, data, { 'ID Evento': eventId }));
  qsSetByHeader_(sheet, headers, row, 'Acción', '');
}

function qsReadAgendaRow_(sheet, headers, row) {
  const values = sheet.getRange(row, 1, 1, sheet.getLastColumn()).getValues()[0];
  const out = {};
  Object.keys(headers).forEach(key => {
    out[qsHeaderLabel_(key)] = values[headers[key] - 1];
  });
  return out;
}

function qsHeaderLabel_(normalized) {
  const labels = {
    'encargada': 'Encargada',
    'día': 'Día',
    'fecha': 'Fecha',
    'hora': 'Hora',
    'servicio': 'Servicio',
    'cantidad': 'Cantidad',
    'clienta': 'Clienta',
    'teléfono': 'Teléfono',
    'dirección': 'Dirección',
    'comuna': 'Comuna',
    'traslado': 'Traslado',
    'abono': 'Abono',
    'fecha abono': 'Fecha Abono',
    'valor servicio': 'Valor Servicio',
    'total servicio': 'Total servicio',
    'total por pagar': 'Total por pagar',
    'acción': 'Acción',
    'estado evento': 'Estado Evento',
    'id evento': 'ID Evento',
    'estado pago': 'Estado Pago'
  };
  return labels[normalized] || normalized;
}

function qsSetByHeader_(sheet, headers, row, header, value) {
  const col = headers[qsNormalizeHeader_(header)];
  if (col) sheet.getRange(row, col).setValue(value);
}

function qsCheckCalendarAccess() {
  const calendar = CalendarApp.getCalendarById(QS_FORM_CONFIG.calendarId);
  if (!calendar) {
    throw new Error('La cuenta ejecutora no tiene acceso al calendario de Qamiluna: ' + QS_FORM_CONFIG.calendarId);
  }
  return {
    id: calendar.getId(),
    name: calendar.getName(),
    timeZone: calendar.getTimeZone(),
    defaultGuest: QS_FORM_CONFIG.defaultCalendarGuest,
    inviteAssignedStaff: QS_FORM_CONFIG.inviteAssignedStaff
  };
}

function qsCalendarGuests_(assignedStaff) {
  const guests = [QS_FORM_CONFIG.defaultCalendarGuest];
  if (!QS_FORM_CONFIG.inviteAssignedStaff) return guests.filter(Boolean);

  const normalized = String(assignedStaff || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

  Object.keys(QS_FORM_CONFIG.assignedStaffEmails).forEach(function (name) {
    const pattern = new RegExp('(^|[^a-z])' + name + '([^a-z]|$)');
    if (pattern.test(normalized)) guests.push(QS_FORM_CONFIG.assignedStaffEmails[name]);
  });

  return Array.from(new Set(guests.filter(Boolean)));
}

function qsCheckCalendarGuestResolution() {
  return {
    Mou: qsCalendarGuests_('Mou'),
    Paz: qsCalendarGuests_('Paz'),
    Cami: qsCalendarGuests_('Cami'),
    'Mou - Paz': qsCalendarGuests_('Mou - Paz'),
    'Cami - Paz': qsCalendarGuests_('Cami - Paz')
  };
}

function qsCreateCalendarEvent_(data) {
  const calendar = CalendarApp.getCalendarById(QS_FORM_CONFIG.calendarId);
  if (!calendar) {
    throw new Error('No se puede crear la cita: la cuenta ejecutora no tiene acceso al calendario ' + QS_FORM_CONFIG.calendarId);
  }
  const start = qsCombineDateTime_(data.Fecha, data.Hora);
  const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);
  const title = `QS | ${data.Servicio} | ${data.Clienta || 'Sin clienta'}`;
  const location = [data.Dirección, data.Comuna].filter(Boolean).join(', ');
  const description = [
    `Clienta: ${data.Clienta || ''}`,
    `Teléfono: ${data['Teléfono'] || ''}`,
    `Encargada: ${data.Encargada || ''}`,
    `Servicio: ${data.Servicio || ''}`,
    `Cantidad: ${data.Cantidad || ''}`,
    `Traslado: ${data.Traslado || 0}`,
    `Abono: ${data.Abono || 0}`,
    `Total servicio: ${data['Total servicio'] || ''}`,
    `Saldo: ${data['Total por pagar'] || ''}`,
    'Invitados: coordinación QS y profesionales seleccionadas',
    'Fuente: Formulario QS'
  ].join('\n');
  const event = calendar.createEvent(title, start, end, {
    location,
    description,
    guests: qsCalendarGuests_(data.Encargada).join(','),
    sendInvites: true
  });
  return event.getId();
}

function qsUpsertBitacora_(agendaSheet, agendaRow, data) {
  const ss = SpreadsheetApp.openById(QS_FORM_CONFIG.bitacoraSpreadsheetId);
  const sheet = ss.getSheetByName(QS_FORM_CONFIG.bitacoraSheet);
  if (!sheet) throw new Error('No existe la hoja Bitácora: ' + QS_FORM_CONFIG.bitacoraSheet);

  const ref = `Agenda: ${agendaSheet.getName()}!${agendaRow}`;
  const existing = qsFindBitacoraByReference_(sheet, ref);
  const row = existing || qsFindFirstEmptyBitacoraRow_(sheet);
  if (!existing) qsPrepareBitacoraRow_(sheet, row);

  const id = existing ? sheet.getRange(row, 1).getValue() : qsNextBitacoraId_(sheet);
  const total = Number(data['Total servicio']) || (Number(data['Valor Servicio']) || 0) + (Number(data.Traslado) || 0);
  const estadoPago = data['Estado Pago'] || ((Number(data.Abono) || 0) >= total && total > 0 ? 'Pagado' : ((Number(data.Abono) || 0) > 0 ? 'Parcial' : 'Pendiente'));
  const estadoServicio = qsDateKey_(data.Fecha) < qsDateKey_(new Date()) ? 'Ejecutado' : 'Pendiente';

  const values = [
    id,
    data.Fecha || '',
    qsDisplayTime_(data.Hora),
    data.Día || 'Ceremonia',
    data.Encargada || '',
    data.Servicio || '',
    `=IF(F${row}="";"";IFS(REGEXMATCH(F${row};"^Social");"Social";REGEXMATCH(F${row};"^Graduación");"Graduación";REGEXMATCH(F${row};"^(Novia|Prueba de)");"Novias";REGEXMATCH(F${row};"^Taller");"Taller";REGEXMATCH(F${row};"^(Manicure|Glitter|Evento)");"Otros";TRUE;IFERROR(TRIM(LEFT(F${row};SEARCH(":";F${row})-1));F${row})))`,
    data.Clienta || '',
    data.Comuna || '',
    Number(data.Traslado) || 0,
    Number(data.Abono) || 0,
    Number(data['Valor Servicio']) || 0,
    total,
    `=IF($M${row}="";"";IF(LOWER($P${row})="pagado";0;$M${row}-$K${row}))`,
    'Pendiente',
    estadoPago,
    estadoServicio,
    'Creado desde Formulario QS',
    data.Dirección || '',
    data['ID Evento'] || '',
    ref,
    `=TEXT(B${row}; "yyyy-mm")`,
    data.Servicio || '',
    `=IF(A${row}="";"";IF(W${row}="Evento: Glitter Bar";"LEGACY";IF(W${row}<>F${row};"HOMOLOGADO";IF(OR(W${row}="Social: 1 Maquillaje + 2 Peinados";W${row}="Mixto: Peinado Social + M+P Novia + Maquillaje Social";W${row}="Novia Civil: Servicio";W${row}="Novia Civil: M+P + Prueba Maquillaje Presencial";W${row}="Novia Civil: M+P + Prueba M+P Presencial";W${row}="Prueba: M+P";W${row}="Novia Fiesta: M+P + Prueba Maquillaje Presencial";W${row}="Taller: Grupal (7 personas)";W${row}="Taller: Grupal (2 personas)";W${row}="Taller: Automaquillaje y Peinado");"REVISAR";"VIGENTE"))))`
  ];
  sheet.getRange(row, 1, 1, values.length).setValues([values]);
}

function qsFindBitacoraByReference_(sheet, ref) {
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return null;
  const refs = sheet.getRange(2, 21, lastRow - 1, 1).getDisplayValues();
  for (let i = 0; i < refs.length; i++) {
    if (refs[i][0] === ref) return i + 2;
  }
  return null;
}

function qsGetBitacoraEventIdByReference_(ref) {
  const ss = SpreadsheetApp.openById(QS_FORM_CONFIG.bitacoraSpreadsheetId);
  const sheet = ss.getSheetByName(QS_FORM_CONFIG.bitacoraSheet);
  if (!sheet) return '';
  const row = qsFindBitacoraByReference_(sheet, ref);
  return row ? String(sheet.getRange(row, 20).getValue() || '') : '';
}

function qsFindFirstEmptyBitacoraRow_(sheet) {
  const lastRow = sheet.getLastRow();
  const ids = sheet.getRange(2, 1, Math.max(lastRow - 1, 1), 1).getDisplayValues();
  for (let i = 0; i < ids.length; i++) {
    if (!ids[i][0]) return i + 2;
  }
  return lastRow + 1;
}

function qsPrepareBitacoraRow_(sheet, row) {
  if (row <= 2) return;
  const source = sheet.getRange(row - 1, 1, 1, sheet.getLastColumn());
  const target = sheet.getRange(row, 1, 1, sheet.getLastColumn());
  source.copyTo(target, SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
  sheet.getRange(row, 1, 1, 24).clearContent();
}

function qsNextBitacoraId_(sheet) {
  const ids = sheet.getRange(2, 1, Math.max(sheet.getLastRow() - 1, 1), 1).getDisplayValues().flat();
  const max = ids.reduce((acc, id) => {
    const match = String(id || '').match(/^QS-(\d+)/);
    return match ? Math.max(acc, Number(match[1])) : acc;
  }, 0);
  return 'QS-' + String(max + 1).padStart(3, '0');
}

function qsCombineDateTime_(dateValue, timeValue) {
  const date = dateValue instanceof Date ? new Date(dateValue) : new Date(dateValue);
  if (timeValue instanceof Date) {
    date.setHours(timeValue.getHours(), timeValue.getMinutes(), 0, 0);
    return date;
  }
  const match = String(timeValue || '09:00').match(/(\d{1,2}):(\d{2})/);
  date.setHours(match ? Number(match[1]) : 9, match ? Number(match[2]) : 0, 0, 0);
  return date;
}

function qsDisplayTime_(value) {
  if (value instanceof Date) return Utilities.formatDate(value, Session.getScriptTimeZone(), 'HH:mm');
  if (typeof value === 'number') {
    const minutes = Math.round(value * 24 * 60);
    return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');
  }
  return String(value || '');
}

function qsReprocesarAgendaFila(sheetName, row) {
  const ss = qsGetSpreadsheet_();
  const sheet = ss.getSheetByName(sheetName);
  if (!sheet) throw new Error('No existe la hoja: ' + sheetName);
  const headers = qsGetHeaderMap_(sheet);
  qsSyncAgendaRow_(sheet, headers, row);
}

function qsRepararFebrero5() {
  qsReprocesarAgendaFila('Febrero', 5);
}

function qsMergeWorkshopSubmission_(sheet, headers, row, rowData, nuevaClienta) {
  const cantidadCol = headers[qsNormalizeHeader_('Cantidad')];
  const clientaCol = headers[qsNormalizeHeader_('Clienta')];
  const telefonoCol = headers[qsNormalizeHeader_('Teléfono')];
  const abonoCol = headers[qsNormalizeHeader_('Abono')];
  const valorCol = headers[qsNormalizeHeader_('Valor Servicio')];
  const trasladoCol = headers[qsNormalizeHeader_('Traslado')];
  const accionCol = headers[qsNormalizeHeader_('Acción')];
  const estadoPagoCol = headers[qsNormalizeHeader_('Estado Pago')];

  if (cantidadCol) {
    const actual = Number(sheet.getRange(row, cantidadCol).getValue()) || 0;
    sheet.getRange(row, cantidadCol).setValue(actual + (rowData['Cantidad'] || 1));
  }
  if (clientaCol) {
    const actual = String(sheet.getRange(row, clientaCol).getValue() || '').trim();
    sheet.getRange(row, clientaCol).setValue(actual ? actual + ' / ' + nuevaClienta : nuevaClienta);
  }
  if (telefonoCol && rowData['Teléfono']) {
    const actual = String(sheet.getRange(row, telefonoCol).getValue() || '').trim();
    sheet.getRange(row, telefonoCol).setValue(actual ? actual + ' / ' + rowData['Teléfono'] : rowData['Teléfono']);
  }
  if (abonoCol) {
    const actual = Number(sheet.getRange(row, abonoCol).getValue()) || 0;
    sheet.getRange(row, abonoCol).setValue(actual + (rowData['Abono'] || 0));
  }
  if (valorCol) {
    const actual = Number(sheet.getRange(row, valorCol).getValue()) || 0;
    sheet.getRange(row, valorCol).setValue(actual + (rowData['Valor Servicio'] || 0));
  }
  if (trasladoCol) {
    const actual = Number(sheet.getRange(row, trasladoCol).getValue()) || 0;
    sheet.getRange(row, trasladoCol).setValue(actual + (rowData['Traslado'] || 0));
  }
  if (accionCol) sheet.getRange(row, accionCol).setValue('ACTUALIZAR');
  if (estadoPagoCol) sheet.getRange(row, estadoPagoCol).setValue((Number(sheet.getRange(row, abonoCol).getValue()) || 0) > 0 ? 'Parcial' : 'Pendiente');
  qsWriteAgendaRow_(sheet, headers, row, {});
}

function qsFindGroupedWorkshopRow_(sheet, headers, fecha, servicio) {
  const fechaCol = headers[qsNormalizeHeader_('Fecha')];
  const servicioCol = headers[qsNormalizeHeader_('Servicio')];
  if (!fechaCol || !servicioCol) return null;

  const lastRow = Math.max(sheet.getLastRow(), 2);
  const data = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  const targetKey = qsDateKey_(fecha);
  for (let i = 0; i < data.length; i++) {
    const rowFecha = data[i][fechaCol - 1];
    const rowServicio = data[i][servicioCol - 1];
    if (qsDateKey_(rowFecha) === targetKey && String(rowServicio).trim() === servicio) {
      return i + 2;
    }
  }
  return null;
}

function qsFindFirstEmptyAgendaRow_(sheet, headers) {
  const fechaCol = headers[qsNormalizeHeader_('Fecha')];
  const servicioCol = headers[qsNormalizeHeader_('Servicio')];
  const clientaCol = headers[qsNormalizeHeader_('Clienta')];
  const lastScan = Math.max(sheet.getMaxRows(), 2);
  const data = sheet.getRange(2, 1, lastScan - 1, sheet.getLastColumn()).getValues();
  for (let i = 0; i < data.length; i++) {
    const empty = !data[i][fechaCol - 1] && !data[i][servicioCol - 1] && !data[i][clientaCol - 1];
    if (empty) return i + 2;
  }
  sheet.insertRowAfter(sheet.getMaxRows());
  return sheet.getMaxRows();
}

function qsGetHeaderMap_(sheet) {
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
  return headers.reduce((acc, name, idx) => {
    const key = qsNormalizeHeader_(name);
    if (key) acc[key] = idx + 1;
    return acc;
  }, {});
}

function qsMapFormResponse_(e) {
  if (!e || !e.response) throw new Error('Evento onFormSubmit inválido.');
  return e.response.getItemResponses().reduce((acc, itemResponse) => {
    acc[itemResponse.getItem().getTitle()] = itemResponse.getResponse();
    return acc;
  }, {});
}

function qsGetSpreadsheet_() {
  return SpreadsheetApp.openById(QS_FORM_CONFIG.spreadsheetId);
}

function qsReadList_(sheet, a1) {
  return sheet.getRange(a1).getDisplayValues()
    .map(row => String(row[0] || '').trim())
    .filter(Boolean);
}

function qsRequiredText_(value, label) {
  const text = String(value || '').trim();
  if (!text) throw new Error('Campo obligatorio vacío: ' + label);
  return text;
}

function qsRequiredDate_(value, label) {
  const parsed = qsCalendarDate_(value);
  if (parsed) return parsed;
  throw new Error('Fecha inválida en campo: ' + label);
}

function qsOptionalDate_(value) {
  return qsCalendarDate_(value) || '';
}

function qsCalendarDate_(value) {
  if (!value) return null;

  if (value instanceof Date && !isNaN(value.getTime())) {
    return new Date(value.getUTCFullYear(), value.getUTCMonth(), value.getUTCDate(), 12, 0, 0);
  }

  const text = String(value).trim();
  let match = text.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
  if (match) return new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]), 12, 0, 0);

  match = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12, 0, 0);

  return null;
}

function qsParseMoney_(value) {
  if (typeof value === 'number') return value;
  const clean = String(value || '0').replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.');
  const parsed = Number(clean);
  return isNaN(parsed) ? 0 : parsed;
}

function qsParseInteger_(value, fallback) {
  const parsed = parseInt(String(value || '').replace(/[^\d-]/g, ''), 10);
  return isNaN(parsed) ? fallback : parsed;
}

function qsNormalizeTime_(value) {
  if (value instanceof Date) {
    return Utilities.formatDate(value, Session.getScriptTimeZone(), 'HH:mm');
  }
  return String(value || '').trim();
}

function qsNormalizeHeader_(value) {
  return String(value || '').trim().toLowerCase();
}

function qsAsArray_(value) {
  if (Array.isArray(value)) return value;
  if (!value) return [];
  return String(value).split(',').map(v => v.trim());
}

function qsDateKey_(value) {
  if (!value) return '';
  const date = value instanceof Date ? value : new Date(value);
  if (isNaN(date.getTime())) return String(value);
  return Utilities.formatDate(date, Session.getScriptTimeZone(), 'yyyy-MM-dd');
}

function qsCol_(col) {
  let temp = col;
  let letter = '';
  while (temp > 0) {
    const rem = (temp - 1) % 26;
    letter = String.fromCharCode(65 + rem) + letter;
    temp = Math.floor((temp - rem - 1) / 26);
  }
  return letter;
}


function qsNormalizeTypedResponse_(respuesta) {
  const tipo = String(respuesta['Tipo de registro'] || 'Servicio regular').trim();
  respuesta['Tipo de registro'] = tipo;
  respuesta['Servicio Principal'] =
    respuesta['Servicio Regular'] ||
    respuesta['Servicio de Novia'] ||
    respuesta['Servicio de Taller'] ||
    respuesta['Pack Glitter Bar'] ||
    respuesta['Servicio Principal'] || '';
  respuesta['Encargada'] = respuesta['Equipo Glitter Bar'] || respuesta['Encargada'] || 'Por asignar';
  respuesta['Cantidad'] = tipo === 'Servicio regular' ? (respuesta['Cantidad'] || 1) : 1;
  respuesta['¿Requiere Prueba?'] = respuesta['¿Requiere Prueba?'] || 'No';
  respuesta['Traslado'] = respuesta['Traslado'] || 0;
  respuesta['Abono'] = respuesta['Abono'] || 0;
  respuesta['Valor Servicio'] = respuesta['Valor Servicio'] || respuesta['Valor Pack'] || respuesta['Valor por Participante'] || 0;
  respuesta['¿Agregar servicios adicionales para acompañantes?'] =
    tipo === 'Servicio regular' ? (respuesta['¿Agregar servicios adicionales para acompañantes?'] || 'No') : 'No';

  const notas = [];
  notas.push('Tipo registro: ' + tipo);
  if (respuesta['ID Sesión Taller']) notas.push('Sesión taller: ' + respuesta['ID Sesión Taller']);
  if (respuesta['Cantidad estimada de asistentes']) notas.push('Asistentes estimados: ' + respuesta['Cantidad estimada de asistentes']);
  if (respuesta['Hora de término']) notas.push('Hora término: ' + respuesta['Hora de término']);
  if (respuesta['Cantidad de encargadas']) notas.push('Cantidad encargadas: ' + respuesta['Cantidad de encargadas']);
  if (respuesta['Horas adicionales']) notas.push('Horas adicionales: ' + respuesta['Horas adicionales']);
  if (String(respuesta['¿Requiere Prueba?'] || '').toLowerCase().indexOf('s') === 0) {
    notas.push('Prueba requerida');
    if (respuesta['Lugar de la Prueba']) notas.push('Lugar prueba: ' + respuesta['Lugar de la Prueba']);
    if (respuesta['Dirección de la Prueba']) notas.push('Dirección prueba: ' + respuesta['Dirección de la Prueba']);
  }
  if (respuesta['Observaciones del registro']) notas.push(respuesta['Observaciones del registro']);
  respuesta['Observaciones'] = notas.join(' | ');
  return respuesta;
}

function qsMigrarFormularioPorTipoV2() {
  const ss = qsGetSpreadsheet_();
  const cfg = ss.getSheetByName(QS_FORM_CONFIG.configSheet);
  if (!cfg) throw new Error('No existe la hoja Config.');

  const servicios = qsReadList_(cfg, QS_FORM_CONFIG.serviceRangeA1);
  const encargadas = qsReadList_(cfg, QS_FORM_CONFIG.encargadasRangeA1);
  const adicionales = qsReadList_(cfg, QS_FORM_CONFIG.additionalServicesRangeA1);
  const glitter = servicios.filter(v => /glitter bar/i.test(v));
  const talleres = servicios.filter(v => /taller/i.test(v));
  const novias = servicios.filter(v => /novia|prueba de maquillaje|prueba de peinado/i.test(v));
  const regulares = servicios.filter(v => !glitter.includes(v) && !talleres.includes(v) && !novias.includes(v));
  if (![regulares, novias, talleres, glitter].every(list => list.length)) {
    throw new Error('La clasificación dejó una categoría sin servicios. Revisa Config.');
  }

  const form = FormApp.openById(QS_FORM_CONFIG.formId);
  const items = form.getItems();
  for (let i = items.length - 1; i >= 0; i--) form.deleteItem(items[i]);

  form.setTitle(QS_FORM_CONFIG.formTitle);
  form.setDescription(
    'Formulario interno para registrar reservas QS en Agenda 2026. ' +
    'Selecciona el tipo de registro para mostrar solamente los campos correspondientes.'
  );
  form.setCollectEmail(false);

  const moneyValidation = FormApp.createTextValidation()
    .requireNumberGreaterThanOrEqualTo(0)
    .setHelpText('Ingresa un monto numérico igual o mayor que 0.')
    .build();
  const wholeValidation = FormApp.createTextValidation()
    .requireWholeNumber()
    .requireNumberGreaterThanOrEqualTo(1)
    .setHelpText('Ingresa un número entero igual o mayor que 1.')
    .build();
  const timeValidation = FormApp.createTextValidation()
    .requireTextMatchesPattern('^([01]?[0-9]|2[0-3]):[0-5][0-9]$')
    .setHelpText('Usa formato HH:MM, por ejemplo 09:30.')
    .build();
  const phoneValidation = FormApp.createTextValidation()
    .requireTextMatchesPattern('^[+0-9][0-9\\s-]{7,20}$')
    .setHelpText('Ingresa un teléfono válido, por ejemplo +56912345678.')
    .build();

  form.addSectionHeaderItem().setTitle('Datos de contacto');
  form.addTextItem().setTitle('Nombre de la Clienta').setRequired(true);
  form.addTextItem().setTitle('Teléfono').setValidation(phoneValidation).setRequired(true);
  const router = form.addMultipleChoiceItem().setTitle('Tipo de registro').setRequired(true);

  const regularPage = form.addPageBreakItem().setTitle('Servicio regular');
  qsAddLocationV2_(form);
  form.addListItem().setTitle('Encargada').setChoiceValues(encargadas).setRequired(true);
  form.addDateItem().setTitle('Fecha del Servicio').setRequired(true);
  form.addTextItem().setTitle('Hora del Servicio').setValidation(timeValidation).setRequired(true);
  form.addListItem().setTitle('Servicio Regular').setChoiceValues(regulares).setRequired(true);
  form.addTextItem().setTitle('Cantidad').setValidation(wholeValidation).setRequired(true);
  qsAddPaymentV2_(form, moneyValidation, 'Valor Servicio');
  form.addMultipleChoiceItem().setTitle('¿Agregar servicios adicionales para acompañantes?').setChoiceValues(['Sí','No']).setRequired(true);
  form.addCheckboxItem().setTitle('Servicios Adicionales').setChoiceValues(adicionales).setRequired(false);
  form.addTextItem().setTitle('Cantidad Acompañantes').setValidation(wholeValidation).setRequired(false);
  form.addTextItem().setTitle('Valor de Acompañantes').setValidation(moneyValidation).setRequired(false);
  form.addParagraphTextItem().setTitle('Observaciones del registro').setRequired(false);

  const bridePage = form.addPageBreakItem().setTitle('Servicio de novia');
  bridePage.setGoToPage(FormApp.PageNavigationType.SUBMIT);
  qsAddLocationV2_(form);
  form.addListItem().setTitle('Encargada').setChoiceValues(encargadas).setRequired(true);
  form.addDateItem().setTitle('Fecha del Servicio').setRequired(true);
  form.addTextItem().setTitle('Hora del Servicio').setValidation(timeValidation).setRequired(true);
  form.addListItem().setTitle('Servicio de Novia').setChoiceValues(novias).setRequired(true);
  form.addMultipleChoiceItem().setTitle('¿Requiere Prueba?').setChoiceValues(['Sí','No']).setRequired(true);
  form.addDateItem().setTitle('Fecha de la Prueba').setRequired(false);
  form.addTextItem().setTitle('Hora de la Prueba').setValidation(timeValidation).setRequired(false);
  qsAddPaymentV2_(form, moneyValidation, 'Valor Servicio');
  form.addParagraphTextItem().setTitle('Observaciones del registro').setRequired(false);

  const workshopPage = form.addPageBreakItem().setTitle('Participante de taller');
  workshopPage.setGoToPage(FormApp.PageNavigationType.SUBMIT);
  qsAddLocationV2_(form);
  form.addListItem().setTitle('Encargada').setChoiceValues(encargadas).setRequired(true);
  form.addDateItem().setTitle('Fecha del Servicio').setRequired(true);
  form.addTextItem().setTitle('Hora del Servicio').setValidation(timeValidation).setRequired(true);
  form.addListItem().setTitle('Servicio de Taller').setChoiceValues(talleres).setRequired(true);
  form.addTextItem().setTitle('ID Sesión Taller').setHelpText('Mismo identificador para todos los participantes de una sesión.').setRequired(true);
  qsAddPaymentV2_(form, moneyValidation, 'Valor por Participante');
  form.addParagraphTextItem().setTitle('Observaciones del registro').setRequired(false);

  const glitterPage = form.addPageBreakItem().setTitle('Evento Glitter Bar');
  glitterPage.setGoToPage(FormApp.PageNavigationType.SUBMIT);
  qsAddLocationV2_(form);
  form.addCheckboxItem().setTitle('Equipo Glitter Bar').setChoiceValues(encargadas).setRequired(true);
  form.addDateItem().setTitle('Fecha del Servicio').setRequired(true);
  form.addTextItem().setTitle('Hora del Servicio').setValidation(timeValidation).setRequired(true);
  form.addTextItem().setTitle('Hora de término').setValidation(timeValidation).setRequired(true);
  form.addListItem().setTitle('Pack Glitter Bar').setChoiceValues(glitter).setRequired(true);
  form.addTextItem().setTitle('Cantidad estimada de asistentes').setValidation(wholeValidation).setRequired(true);
  form.addTextItem().setTitle('Cantidad de encargadas').setValidation(wholeValidation).setRequired(true);
  form.addTextItem().setTitle('Horas adicionales').setValidation(wholeValidation).setRequired(false);
  qsAddPaymentV2_(form, moneyValidation, 'Valor Pack');
  form.addParagraphTextItem().setTitle('Observaciones del registro').setRequired(false);

  const endPage = form.addPageBreakItem().setTitle('Registro listo');
  endPage.setHelpText('Revisa tus respuestas y envía el formulario.');
  endPage.setGoToPage(FormApp.PageNavigationType.SUBMIT);

  router.setChoices([
    router.createChoice('Servicio regular', regularPage),
    router.createChoice('Novia', bridePage),
    router.createChoice('Taller', workshopPage),
    router.createChoice('Glitter Bar', glitterPage)
  ]);

  PropertiesService.getScriptProperties().setProperty('QS_FORM_SCHEMA_VERSION', '2');
  return {
    formId: form.getId(),
    publicUrl: form.getPublishedUrl(),
    regular: regulares.length,
    bride: novias.length,
    workshop: talleres.length,
    glitter: glitter.length
  };
}

function qsAddLocationV2_(form) {
  form.addTextItem().setTitle('Dirección').setRequired(true);
  form.addTextItem().setTitle('Comuna').setRequired(true);
}

function qsAddPaymentV2_(form, validation, valueTitle) {
  form.addTextItem().setTitle('Traslado').setValidation(validation).setRequired(true);
  form.addTextItem().setTitle('Abono').setValidation(validation).setRequired(true);
  form.addDateItem().setTitle('Fecha de Abono').setRequired(false);
  form.addTextItem().setTitle(valueTitle).setValidation(validation).setRequired(true);
}


const QS_QUOTE_CONFIG = Object.freeze({
  travelSpreadsheetId: '1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE',
  travelSheet: 'Tarifario Traslados',
  serviceSpreadsheetId: '1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE',
  serviceSheet: 'Servicios'
});
const QS_QUOTE_CACHE_SECONDS = 300;

function doGet() {
  return HtmlService.createTemplateFromFile('Cotizador')
    .evaluate()
    .setTitle('Cotizador y Agenda QS')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function qsGetQuoteCatalog() {
  const cached = qsCacheGetJson_('quote_catalog_v2');
  if (cached) return cached;

  const ss = qsGetSpreadsheet_();
  const configSheet = ss.getSheetByName(QS_FORM_CONFIG.configSheet);
  const serviceSheet = SpreadsheetApp.openById(QS_QUOTE_CONFIG.serviceSpreadsheetId)
    .getSheetByName(QS_QUOTE_CONFIG.serviceSheet);
  if (!serviceSheet || !configSheet) throw new Error('No se encontraron las hojas maestras Servicios o Config.');

  const serviceRows = serviceSheet.getRange(5, 1, Math.max(serviceSheet.getLastRow() - 4, 1), 5).getValues();
  const services = serviceRows
    .filter(row => /^s[ií]$/i.test(String(row[0] || '').trim()) && String(row[2] || '').trim())
    .map(row => {
      const name = String(row[2]).trim();
      const type = qsQuoteType_(name);
      return {
        name,
        label: name,
        price: Number(row[4]) || 0,
        sale_price: Number(row[4]) || 0,
        defaultQuantity: Number(row[3]) || 1,
        type,
        tipo_servicio: type
      };
    })
    .filter(item => item.price > 0)
    .filter((item, index, list) => list.findIndex(other => other.name === item.name) === index);
  const serviceTypes = ['Todos'].concat(
    Object.keys(services.reduce((types, service) => {
      if (service.type) types[service.type] = true;
      return types;
    }, {})).sort()
  );

  const travelSheet = SpreadsheetApp.openById(QS_QUOTE_CONFIG.travelSpreadsheetId)
    .getSheetByName(QS_QUOTE_CONFIG.travelSheet);
  if (!travelSheet) throw new Error('No se encontró la hoja de tarifas de traslado.');
  const travelRows = travelSheet.getRange(6, 1, Math.max(travelSheet.getLastRow() - 5, 1), 6).getValues();
  const communes = travelRows
    .filter(row => String(row[0] || '').trim())
    .map(row => ({ name: String(row[0]).trim(), price: Number(row[5]) || 0 }));

  const catalog = {
    service_types: serviceTypes,
    services,
    communes,
    staff: qsReadList_(configSheet, QS_FORM_CONFIG.encargadasRangeA1)
      .filter(name => !/\byeimy\b/i.test(String(name)))
  };
  qsCachePutJson_('quote_catalog_v2', catalog, QS_QUOTE_CACHE_SECONDS);
  return catalog;
}

function qsGetServiceQuote(serviceName) {
  const requested = String(serviceName || '').trim();
  if (!requested) return { name: '', price: 0, defaultQuantity: 1, type: '' };
  const cacheKey = 'service_quote_v2_' + qsNormalizeTextKey_(requested);
  const cached = qsCacheGetJson_(cacheKey);
  if (cached) return cached;
  const sheet = SpreadsheetApp.openById(QS_QUOTE_CONFIG.serviceSpreadsheetId)
    .getSheetByName(QS_QUOTE_CONFIG.serviceSheet);
  if (!sheet) throw new Error('No se encontró la hoja maestra Servicios.');
  SpreadsheetApp.flush();
  const rows = sheet.getRange(5, 1, Math.max(sheet.getLastRow() - 4, 1), 5).getValues();
  const key = qsNormalizeTextKey_(requested);
  const row = rows.find(item => /^s[ií]$/i.test(String(item[0] || '').trim()) && qsNormalizeTextKey_(item[2]) === key);
  if (!row) throw new Error('El servicio no está activo en la hoja maestra.');
  const price = Number(row[4]) || 0;
  if (price <= 0) throw new Error('El servicio no tiene un precio de venta válido.');
  const quote = { name: String(row[2]).trim(), price, defaultQuantity: Number(row[3]) || 1, type: qsQuoteType_(row[2]) };
  qsCachePutJson_(cacheKey, quote, QS_QUOTE_CACHE_SECONDS);
  return quote;
}

function qsGetTravelQuote(commune) {
  const requested = String(commune || '').trim();
  if (!requested) return { name: '', price: 0 };
  const cacheKey = 'travel_quote_v2_' + qsNormalizeTextKey_(requested);
  const cached = qsCacheGetJson_(cacheKey);
  if (cached) return cached;
  const sheet = SpreadsheetApp.openById(QS_QUOTE_CONFIG.travelSpreadsheetId)
    .getSheetByName(QS_QUOTE_CONFIG.travelSheet);
  if (!sheet) throw new Error('No se encontró Tarifario Traslados.');
  SpreadsheetApp.flush();
  const rows = sheet.getRange(6, 1, Math.max(sheet.getLastRow() - 5, 1), 6).getValues();
  const key = qsNormalizeTextKey_(requested);
  const row = rows.find(item => qsNormalizeTextKey_(item[0]) === key);
  if (!row) throw new Error('La comuna seleccionada no existe en el tarifario vigente.');
  const price = Number(row[5]) || 0;
  if (price <= 0) throw new Error('La comuna no tiene una tarifa final válida.');
  const quote = { name: String(row[0]).trim(), price: price };
  qsCachePutJson_(cacheKey, quote, QS_QUOTE_CACHE_SECONDS);
  return quote;
}

function qsTravelDiscountRate_(serviceCount) {
  if (serviceCount > 2) return 0.4;
  if (serviceCount === 2) return 0.2;
  return 0;
}

function qsTravelDiscountReason_(serviceCount) {
  if (serviceCount > 2) return '40% por más de 2 servicios';
  if (serviceCount === 2) return '20% por servicio extra';
  return 'sin descuento';
}

function qsCacheGetJson_(key) {
  const raw = CacheService.getScriptCache().get(key);
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch (error) {
    return null;
  }
}

function qsCachePutJson_(key, value, seconds) {
  CacheService.getScriptCache().put(key, JSON.stringify(value), seconds);
}

function qsInvalidateRuntimeCaches_() {
  const cache = CacheService.getScriptCache();
  cache.remove('quote_catalog_v2');
  cache.remove('pending_bookings_v2');
}

function qsNormalizeTextKey_(value) {
  return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function qsQuoteType_(name) {
  if (/glitter bar/i.test(name)) return 'Glitter Bar';
  if (/taller/i.test(name)) return 'Taller';
  if (/novia|prueba de maquillaje|prueba de peinado/i.test(name)) return 'Novia';
  return 'Servicio regular';
}

function qsNormalizeQuoteServices_(data) {
  const rawServices = Array.isArray(data.services) && data.services.length
    ? data.services
    : [{
      name: data.service,
      type: data.type,
      quantity: data.quantity,
      originalServicePrice: data.originalServicePrice,
      servicePrice: data.servicePrice,
      promo: data.promo,
      discountMode: data.discountMode,
      customPromoPrice: data.customPromoPrice
    }];

  if (!rawServices.length) throw new Error('Agrega al menos un servicio a la reserva.');

  return rawServices.map((item, index) => {
    const name = qsRequiredText_(item.name || item.service, 'Servicio ' + (index + 1));
    const quantity = qsParseInteger_(item.quantity, 1);
    const liveService = qsGetServiceQuote(name);
    const liveTotal = liveService.price * quantity;
    const original = qsParseMoney_(item.originalServicePrice);
    const charged = qsParseMoney_(item.servicePrice);
    if (original !== liveTotal) {
      throw new Error('El precio maestro cambió para "' + name + '". Actualiza la cotización antes de agendar.');
    }
    if (charged <= 0 || charged > liveTotal) {
      throw new Error('El valor cobrado para "' + name + '" no coincide con una cotización válida.');
    }

    const discount = Math.max(0, liveTotal - charged);
    return {
      name: liveService.name,
      type: liveService.type || item.type || qsQuoteType_(name),
      quantity,
      originalServicePrice: liveTotal,
      servicePrice: charged,
      discount,
      promo: item.promo === true || String(item.promo || '').toLowerCase() === 'true',
      discountMode: String(item.discountMode || '')
    };
  });
}

function qsGuardarReservaWeb(payload) {
  const data = payload || {};
  if (String(data.action || '') !== 'schedule') {
    return { saved: false, message: 'Cotización calculada. No se registró ninguna reserva.' };
  }

  const services = qsNormalizeQuoteServices_(data);
  const serviceTotal = services.reduce((sum, item) => sum + item.servicePrice, 0);
  const originalServiceTotal = services.reduce((sum, item) => sum + item.originalServicePrice, 0);
  const serviceDiscountTotal = Math.max(0, originalServiceTotal - serviceTotal);
  const serviceNames = services.map(item => item.quantity > 1 ? item.name + ' x' + item.quantity : item.name);
  const combinedServiceName = serviceNames.join(' + ');
  const serviceTypes = services.reduce((types, item) => {
    if (item.type && types.indexOf(item.type) === -1) types.push(item.type);
    return types;
  }, []);
  const bookingType = serviceTypes.length > 1 ? 'Mixto' : (serviceTypes[0] || data.type || 'Servicio regular');

  const required = {
    'Nombre de la Clienta': data.clientName,
    'Teléfono': data.phone,
    'Dirección': data.address,
    'Comuna': data.commune,
    'Encargada': data.staff,
    'Fecha del Servicio': data.serviceDate,
    'Hora del Servicio': data.serviceTime,
    'Servicio Principal': combinedServiceName
  };
  Object.keys(required).forEach(key => qsRequiredText_(required[key], key));

  const deposit = qsParseMoney_(data.deposit);
  if (deposit <= 0 && !data.depositDueDate) {
    throw new Error('Debes indicar la fecha de cobro del abono cuando la reserva queda sin abono.');
  }
  const requiresProof = data.requiresProof === true || String(data.requiresProof || '').toLowerCase() === 'true';
  const proofPlace = String(data.proofPlace || '').trim();
  const proofAddressMode = String(data.proofAddressMode || '').trim();
  const proofNewAddress = String(data.proofNewAddress || '').trim();
  let proofAddress = '';
  if (requiresProof) {
    qsRequiredDate_(data.proofDate, 'Fecha de la Prueba');
    qsNormalizeTime_(data.proofTime);
    qsRequiredText_(proofPlace, 'Lugar de la Prueba');
    if (proofPlace === 'A domicilio') {
      qsRequiredText_(proofAddressMode, 'Dirección de la Prueba');
      if (proofAddressMode === 'Usar la misma dirección del servicio') proofAddress = data.address;
      if (proofAddressMode === 'Ingresar una dirección nueva') proofAddress = qsRequiredText_(proofNewAddress, 'Nueva dirección de la Prueba');
      if (proofAddressMode === 'Dejar pendiente de definir') proofAddress = 'Pendiente de definir';
    }
  }

  if (qsParseMoney_(data.originalServicePrice) !== originalServiceTotal) {
    throw new Error('La suma de precios maestros cambió. Actualiza la cotización antes de agendar.');
  }
  if (qsParseMoney_(data.servicePrice) !== serviceTotal) {
    throw new Error('La suma de servicios no coincide con la cotización vigente.');
  }

  const includeTravel = data.includeTravel === true || String(data.includeTravel || '').toLowerCase() === 'true';
  const liveTravelBase = includeTravel ? qsGetTravelQuote(data.commune).price : 0;
  const expectedTravelDiscountRate = qsTravelDiscountRate_(services.length);
  const expectedTravelDiscount = includeTravel && expectedTravelDiscountRate > 0 ? Math.round(liveTravelBase * expectedTravelDiscountRate) : 0;
  const expectedTravel = includeTravel ? Math.max(0, liveTravelBase - expectedTravelDiscount) : 0;
  if (qsParseMoney_(data.travelBase) && qsParseMoney_(data.travelBase) !== liveTravelBase) {
    throw new Error('La tarifa de traslado cambió. Actualiza la cotización antes de agendar.');
  }
  if (qsParseMoney_(data.travelDiscount) !== expectedTravelDiscount || qsParseMoney_(data.travelPrice) !== expectedTravel) {
    throw new Error('El descuento de traslado cambió. Actualiza la cotización antes de agendar.');
  }
  const expected = serviceTotal + expectedTravel;
  if (qsParseMoney_(data.total) !== expected) {
    throw new Error('La cotización cambió. Actualiza los valores antes de agendar.');
  }

  const serviceDetailNote = 'Servicios: ' + services.map(item =>
    item.name + ' x' + item.quantity + ' = $' + item.servicePrice +
    (item.discount > 0 ? ' (desc. $' + item.discount + ')' : '')
  ).join(' / ');
  const travelNote = includeTravel
    ? 'Traslado: base $' + liveTravelBase + (expectedTravelDiscount > 0 ? ', descuento ' + qsTravelDiscountReason_(services.length) + ' $' + expectedTravelDiscount + ', cobro $' + expectedTravel : ', cobro $' + expectedTravel)
    : 'Traslado no incluido';
  const promoNote = serviceDiscountTotal > 0
    ? 'Descuento servicios total: $' + serviceDiscountTotal
    : '';

  const response = qsNormalizeTypedResponse_({
    'Tipo de registro': bookingType,
    'Nombre de la Clienta': data.clientName,
    'Teléfono': data.phone,
    'Dirección': data.address,
    'Comuna': includeTravel ? data.commune : (data.commune || 'Sin traslado'),
    'Encargada': data.staff,
    'Equipo Glitter Bar': data.glitterTeam || '',
    'Fecha del Servicio': data.serviceDate,
    'Hora del Servicio': data.serviceTime,
    '¿Requiere Prueba?': requiresProof ? 'Sí' : 'No',
    'Fecha de la Prueba': requiresProof ? data.proofDate : '',
    'Hora de la Prueba': requiresProof ? data.proofTime : '',
    'Lugar de la Prueba': requiresProof ? proofPlace : '',
    'Dirección de la Prueba': requiresProof ? proofAddress : '',
    'Hora de término': data.endTime || '',
    'Servicio Principal': combinedServiceName,
    'Cantidad': 1,
    'Traslado': expectedTravel,
    'Abono': deposit,
    'Fecha de Abono': deposit > 0 ? (data.depositDate || '') : data.depositDueDate,
    'Valor Servicio': serviceTotal,
    'ID Sesión Taller': data.workshopSessionId || '',
    'Cantidad estimada de asistentes': data.attendees || '',
    'Cantidad de encargadas': data.staffCount || '',
    'Horas adicionales': data.extraHours || '',
    'Observaciones del registro': [
      data.notes || '',
      serviceDetailNote,
      travelNote,
      requiresProof
        ? 'Prueba: ' + [data.proofDate, data.proofTime, proofPlace, proofAddress].filter(Boolean).join(' · ')
        : '',
      promoNote,
      deposit > 0 ? 'Abono recibido' : 'Reserva sin abono; cobro programado'
    ].filter(Boolean).join(' | ')
  });

  const serviceDate = qsRequiredDate_(response['Fecha del Servicio'], 'Fecha del Servicio');
  const month = QS_FORM_CONFIG.monthSheets[serviceDate.getMonth()];
  const sheet = qsGetSpreadsheet_().getSheetByName(month);
  if (!sheet) throw new Error('No existe la hoja mensual: ' + month);

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const headers = qsGetHeaderMap_(sheet);
    const rowData = qsBuildAgendaRow_(response, response['Servicio Principal'], qsParseInteger_(response['Cantidad'], 1), serviceDate);
    const row = qsAppendAgendaRow_(sheet, headers, rowData);
    qsSyncAgendaRow_(sheet, headers, row);
    qsInvalidateRuntimeCaches_();
    return { saved: true, sheet: month, row, total: expected, deposit, balance: expected - deposit };
  } finally {
    lock.releaseLock();
  }
}

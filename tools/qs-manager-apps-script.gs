// ============================================================
// QS MANAGER - Google Apps Script
// Calendar + validacion + comentarios + bitacora unificada
// ============================================================

const CALENDAR_ID = "qamilunaservices@qamilunastudio.com";
const BITACORA_ID = "1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE";
const BITACORA_SHEET_NAME = "Bitácora QS — Servicios";
const DURATION_HOURS = 2;
const NOTE_PREFIX = "[QS MANAGER]";
const DEFAULT_GUEST = "";
const ENCARGADA_EMAILS = {
  paz: "vi.espectral@gmail.com",
  mou: "mymarchantc@gmail.com",
  cami: "cami.verdejo@gmail.com",
};

const CAMPOS_CALENDAR = [
  "encargada",
  "fecha",
  "hora",
  "servicio",
  "clienta",
  "telefono",
  "direccion",
];

const CAMPOS_BITACORA = [
  "encargada",
  "fecha",
  "hora",
  "servicio",
  "clienta",
  "direccion",
  "comuna",
  "traslado",
  "abono",
  "valor servicio",
  "total servicio",
];

const CAMPOS_AUTO_SYNC = [
  ...CAMPOS_CALENDAR,
  ...CAMPOS_BITACORA,
  "dia",
  "cantidad",
  "total por pagar",
];

function crearEvento(e) {
  const lock = LockService.getDocumentLock();
  if (!lock.tryLock(30000)) return;

  try {
    procesarEdicion(e);
  } finally {
    lock.releaseLock();
  }
}

function procesarEdicion(e) {
  if (!e || !e.range) return;

  const sheet = e.range.getSheet();
  const row = e.range.getRow();
  if (row <= 1 || e.range.getNumRows() !== 1 || e.range.getNumColumns() !== 1) return;

  const col = mapearEncabezados(sheet);
  const cAccion = col.accion;
  const cEstado = col["estado evento"];
  const cId = col["id evento"];
  const columnaEditada = e.range.getColumn();
  const encabezadoEditado = normalizarTexto(sheet.getRange(1, columnaEditada).getValue());
  const editoAccion = cAccion && columnaEditada === cAccion;
  const editoEstado = cEstado && columnaEditada === cEstado;
  const idEventoInicial = cId ? String(sheet.getRange(row, cId).getValue() || "").trim() : "";
  const cancelarPorEstado = editoEstado
    && normalizarTexto(sheet.getRange(row, cEstado).getValue()).includes("cancel");
  const actualizarAutomaticamente = CAMPOS_AUTO_SYNC.includes(encabezadoEditado) && !!idEventoInicial;
  if (!editoAccion && !cancelarPorEstado && !actualizarAutomaticamente) return;

  const valor = campo => {
    const c = col[normalizarTexto(campo)];
    return c ? sheet.getRange(row, c).getValue() : "";
  };

  const texto = campo => String(valor(campo) == null ? "" : valor(campo)).trim();

  const setEstado = mensaje => {
    if (cEstado) sheet.getRange(row, cEstado).setValue(mensaje);
  };

  const agregarNotaEstado = mensaje => {
    if (!cEstado) return;
    const cell = sheet.getRange(row, cEstado);
    const notaActual = cell.getNote();
    const nuevaNota = NOTE_PREFIX + " " + mensaje;
    cell.setNote(notaActual ? notaActual + "\n" + nuevaNota : nuevaNota);
  };

  const setComentario = (campo, mensaje) => {
    const c = col[normalizarTexto(campo)];
    if (!c) return;
    const cell = sheet.getRange(row, c);
    const notaActual = limpiarNotaQS(cell.getNote());
    const nuevaNota = NOTE_PREFIX + " " + mensaje;
    cell.setNote(notaActual ? notaActual + "\n" + nuevaNota : nuevaNota);
    cell.setBackground("#ffe0e0");
  };

  const limpiarComentarios = () => {
    [...CAMPOS_CALENDAR, ...CAMPOS_BITACORA, "estado evento"].forEach(campo => {
      const c = col[normalizarTexto(campo)];
      if (!c) return;
      const cell = sheet.getRange(row, c);
      const notaActual = cell.getNote();
      if (!notaActual || !notaActual.includes(NOTE_PREFIX)) return;
      cell.setNote(limpiarNotaQS(notaActual));
      if (campo !== "estado evento") cell.setBackground(null);
    });
  };

  limpiarComentarios();

  const accion = editoAccion
    ? texto("accion").toUpperCase()
    : cancelarPorEstado
      ? "CANCELAR"
      : "ACTUALIZAR";
  const isCrear = ["OK", "CREAR"].includes(accion);
  const isActualizar = ["CAMBIAR", "ACTUALIZAR"].includes(accion);
  const isCancelar = ["CANCELAR", "CANCELADO", "BORRAR"].includes(accion);
  const isRecrear = accion === "RECREAR";

  if (!isCrear && !isActualizar && !isCancelar && !isRecrear) {
    setEstado("Accion invalida");
    return;
  }

  const cal = CalendarApp.getCalendarById(CALENDAR_ID);
  if (!cal) {
    setEstado("Error: no se encontro el calendario configurado");
    return;
  }

  let idEvento = cId ? String(sheet.getRange(row, cId).getValue() || "").trim() : "";
  const idEventoDuplicado = idEvento && contarUsoIdEvento_(sheet.getParent(), idEvento) > 1;

  if (idEventoDuplicado && !isRecrear) {
    setEstado("Error: ID Evento duplicado; usar RECREAR");
    return;
  }

  if (isRecrear) {
    try {
      eliminarEventoSeguro_(cal, idEvento);
      idEvento = "";
      if (cId) sheet.getRange(row, cId).setValue("");
    } catch (err) {
      setEstado("Error al recrear: " + err.message);
      return;
    }
  }

  if (isCancelar) {
    try {
      eliminarEventoSeguro_(cal, idEvento);
      eliminarDeBitacora(row, sheet, col, idEvento);
      if (cId) sheet.getRange(row, cId).setValue("");
      setEstado(idEvento ? "CANCELADO" : "CANCELADO - sin evento en Calendar");
    } catch (err) {
      setEstado("Error al cancelar: " + err.message);
    }
    return;
  }

  const erroresCalendar = validarCampos(CAMPOS_CALENDAR, valor);
  erroresCalendar.forEach(campo => {
    setComentario(campo, "Requerido para crear la cita en Calendar");
  });

  const erroresBitacora = validarCampos(CAMPOS_BITACORA, valor);
  erroresBitacora.forEach(campo => {
    setComentario(campo, "Requerido para registrar en Bitacora QS. Usar 0 si no aplica.");
  });

  if (erroresCalendar.length) {
    setEstado("Faltan datos: " + erroresCalendar.join(", "));
    return;
  }

  let startDate;
  try {
    startDate = construirFechaHora(valor("fecha"), valor("hora"));
  } catch (err) {
    setComentario("fecha", err.message);
    setComentario("hora", err.message);
    setEstado("Error en fecha/hora: " + err.message);
    return;
  }

  const endDate = new Date(startDate.getTime() + DURATION_HOURS * 60 * 60 * 1000);
  const encargada = texto("encargada");
  const direccion = texto("direccion");
  const comuna = texto("comuna");
  const clienta = texto("clienta");
  const cantidad = texto("cantidad");
  const invitados = obtenerInvitados(encargada);
  const titulo = texto("servicio") + " - " + clienta
    + (cantidad && cantidad !== "1" ? " (" + cantidad + ")" : "");

  const descripcion = [
    "Clienta: " + clienta,
    "Direccion: " + direccion + (comuna ? ", " + comuna : ""),
    "Encargada: " + encargada,
    texto("dia") ? "Dia: " + texto("dia") : "",
    texto("telefono") ? "Telefono: " + texto("telefono") : "",
    !estaVacio(valor("traslado")) ? "Traslado: " + valorDescripcion(valor("traslado")) : "",
    !estaVacio(valor("abono")) ? "Abono: " + valorDescripcion(valor("abono")) : "",
    !estaVacio(valor("valor servicio"))
      ? "Valor Servicio: " + valorDescripcion(valor("valor servicio"))
      : "",
  ].filter(Boolean).join("\n");

  try {
    let ev = obtenerEventoSeguro_(cal, idEvento);
    let estado;

    if (ev) {
      actualizarEvento(ev, titulo, startDate, endDate, direccion, comuna, descripcion, encargada);
      estado = actualizarAutomaticamente
        ? "ACTUALIZADO - automático"
        : isCrear
          ? "ACTUALIZADO - ya existia"
          : "ACTUALIZADO";
    } else {
      ev = cal.createEvent(titulo, startDate, endDate, {
        location: direccion + (comuna ? ", " + comuna : ""),
        description: descripcion,
        guests: invitados.join(","),
        sendInvites: true,
      });
      sincronizarInvitadosEvento(ev, encargada);
      idEvento = ev.getId();
      if (cId) sheet.getRange(row, cId).setValue(idEvento);
      estado = isRecrear
        ? "RECREADO"
        : isActualizar
          ? "CREADO - desde ACTUALIZAR"
          : "CREADO";
    }

    setEstado(estado);

    if (erroresBitacora.length) {
      agregarNotaEstado("Falta para Bitacora: " + erroresBitacora.join(", "));
      return;
    }

    const resultado = sincronizarBitacora(
      row,
      sheet,
      col,
      estadoServicioDesdeAgenda(texto("estado evento"), valor("fecha")),
      idEvento
    );
    if (!resultado.ok) agregarNotaEstado("Calendar actualizado, pero fallo Bitacora: " + resultado.error);
  } catch (err) {
    setEstado("Error en Calendar: " + err.message);
  }
}

function obtenerEventoSeguro_(cal, idEvento) {
  if (!idEvento) return null;

  try {
    return cal.getEventById(idEvento);
  } catch (err) {
    if (esEventoInexistente_(err)) return null;
    throw err;
  }
}

function eliminarEventoSeguro_(cal, idEvento) {
  const ev = obtenerEventoSeguro_(cal, idEvento);
  if (!ev) return false;

  try {
    ev.deleteEvent();
    return true;
  } catch (err) {
    if (esEventoInexistente_(err)) return false;
    throw err;
  }
}

function esEventoInexistente_(err) {
  const mensaje = normalizarTexto(err && err.message);
  return mensaje.includes("does not exist") || mensaje.includes("already been deleted");
}

function contarUsoIdEvento_(agenda, idEvento) {
  if (!agenda || !idEvento) return 0;
  let usos = 0;

  agenda.getSheets().forEach(sheet => {
    const col = mapearEncabezados(sheet);
    const cId = col["id evento"];
    if (!cId || sheet.getLastRow() <= 1) return;
    const ids = sheet.getRange(2, cId, sheet.getLastRow() - 1, 1).getValues().flat();
    usos += ids.filter(id => String(id || "").trim() === idEvento).length;
  });

  return usos;
}

function actualizarEvento(ev, titulo, startDate, endDate, direccion, comuna, descripcion, encargada) {
  ev.setTitle(titulo);
  ev.setTime(startDate, endDate);
  ev.setLocation(direccion + (comuna ? ", " + comuna : ""));
  ev.setDescription(descripcion);
  sincronizarInvitadosEvento(ev, encargada);
}

function obtenerInvitados(encargada) {
  const encargadaNormalizada = normalizarTexto(encargada);
  const invitados = DEFAULT_GUEST ? [DEFAULT_GUEST] : [];

  Object.keys(ENCARGADA_EMAILS).forEach(nombre => {
    const patron = new RegExp("(^|[^a-z])" + nombre + "([^a-z]|$)");
    if (patron.test(encargadaNormalizada)) invitados.push(ENCARGADA_EMAILS[nombre]);
  });

  return [...new Set(invitados)];
}

function sincronizarInvitadosEvento(ev, encargada) {
  const invitadosDeseados = obtenerInvitados(encargada);
  const correosAdministrados = [DEFAULT_GUEST, ...Object.values(ENCARGADA_EMAILS)];
  const invitadosActuales = ev.getGuestList().map(guest => guest.getEmail());

  invitadosActuales.forEach(email => {
    if (correosAdministrados.includes(email) && !invitadosDeseados.includes(email)) {
      ev.removeGuest(email);
    }
  });

  invitadosDeseados.forEach(email => {
    if (!invitadosActuales.includes(email)) ev.addGuest(email);
  });
}

function obtenerBitacora_() {
  const spreadsheet = SpreadsheetApp.openById(BITACORA_ID);
  return spreadsheet.getSheetByName(BITACORA_SHEET_NAME)
    || spreadsheet.getSheetByName("Bitácora QS — Servicios 2025-2026 v4")
    || spreadsheet.getSheets()[0];
}

function prepararBitacora_() {
  const bitacora = obtenerBitacora_();
  const headersRequeridos = ["Dirección", "ID Calendar", "Referencia Agenda"];
  let lastColumn = Math.max(bitacora.getLastColumn(), 1);
  const headersActuales = bitacora.getRange(1, 1, 1, lastColumn).getValues()[0];
  const headersNormalizados = headersActuales.map(normalizarTexto);

  headersRequeridos.forEach(header => {
    if (headersNormalizados.includes(normalizarTexto(header))) return;
    lastColumn += 1;
    bitacora.getRange(1, lastColumn).setValue(header);
    headersNormalizados.push(normalizarTexto(header));
  });

  const data = bitacora.getDataRange().getValues();
  const headers = data[0].map(normalizarTexto);
  const bitCol = {};
  headers.forEach((header, index) => {
    if (header) bitCol[header] = index;
  });

  return { bitacora, data, headers, bitCol };
}

function buscarFilaBitacora_(data, bitCol, referencia) {
  const coincidenciasCalendar = [];
  const coincidenciasExactas = [];
  const coincidenciasClientaFecha = [];
  const clave = claveRegistro(referencia.fecha, referencia.clienta, referencia.servicio);
  const claveClientaFecha = claveRegistroClientaFecha_(referencia.fecha, referencia.clienta);

  for (let i = 1; i < data.length; i++) {
    const fila = data[i];
    const observaciones = String(fila[bitCol.observaciones] || "");
    const agendaRef = String(fila[bitCol["referencia agenda"]] || "").trim();
    const idCalendar = String(fila[bitCol["id calendar"]] || "").trim();

    if ((referencia.agendaRef && agendaRef === referencia.agendaRef)
        || (referencia.agendaRef && observaciones.includes(referencia.agendaRef))) {
      return i + 1;
    }

    if ((referencia.idCalendar && idCalendar === referencia.idCalendar)
        || (referencia.idCalendar && observaciones.includes(referencia.idCalendar))) {
      coincidenciasCalendar.push(i + 1);
    }
    if (claveRegistro(fila[bitCol.fecha], fila[bitCol.clienta], fila[bitCol.servicio]) === clave) {
      coincidenciasExactas.push(i + 1);
    }
    if (claveRegistroClientaFecha_(fila[bitCol.fecha], fila[bitCol.clienta]) === claveClientaFecha) {
      coincidenciasClientaFecha.push(i + 1);
    }
  }

  if (coincidenciasExactas.length === 1) return coincidenciasExactas[0];
  if (coincidenciasCalendar.length === 1) return coincidenciasCalendar[0];
  if (coincidenciasClientaFecha.length === 1) return coincidenciasClientaFecha[0];
  return -1;
}

function sincronizarBitacora(row, sheet, col, estadoServicio, idCalendar) {
  try {
    const { bitacora, data, headers, bitCol } = prepararBitacora_();

    const valor = campo => {
      const c = col[normalizarTexto(campo)];
      return c ? sheet.getRange(row, c).getValue() : "";
    };

    const texto = campo => String(valor(campo) == null ? "" : valor(campo)).trim();
    const fecha = valor("fecha");
    const clienta = texto("clienta");
    const servicio = estandarizarServicio(texto("servicio"));
    const agendaRef = referenciaAgenda(sheet, row);
    const filaExistente = buscarFilaBitacora_(data, bitCol, {
      agendaRef,
      idCalendar,
      fecha,
      clienta,
      servicio,
    });

    const lastRow = bitacora.getLastRow();
    const ids = lastRow > 1 ? bitacora.getRange(2, 1, lastRow - 1, 1).getValues().flat() : [];
    const maxId = ids.reduce((max, id) => {
      const n = parseInt(String(id).replace("QS-", ""), 10);
      return isNaN(n) ? max : Math.max(max, n);
    }, 0);

    const nuevaFila = filaExistente > 0
      ? data[filaExistente - 1].slice(0, headers.length)
      : Array(headers.length).fill("");

    const set = (header, value) => {
      if (bitCol[normalizarTexto(header)] != null) nuevaFila[bitCol[normalizarTexto(header)]] = value;
    };

    set("id", filaExistente > 0 ? nuevaFila[bitCol.id] : "QS-" + String(maxId + 1).padStart(3, "0"));
    set("fecha", fechaParaBitacora(fecha));
    set("tipo", texto("dia") || "Servicio");
    set("encargada", texto("encargada"));
    set("servicio", servicio);
    set("clienta", clienta);
    set("dirección", texto("direccion"));
    set("comuna", texto("comuna"));
    set("traslado", estaVacio(valor("traslado")) ? 0 : valor("traslado"));
    set("abono", estaVacio(valor("abono")) ? 0 : valor("abono"));
    set("valor servicio", estaVacio(valor("valor servicio")) ? 0 : valor("valor servicio"));
    set("total servicio", estaVacio(valor("total servicio")) ? 0 : valor("total servicio"));
    set("saldo", estaVacio(valor("total por pagar")) ? 0 : valor("total por pagar"));
    set("forma de pago", nuevaFila[bitCol["forma de pago"]] || "Pendiente");
    set("estado pago", estadoPagoDesdeMontos(valor("abono"), valor("total por pagar")));
    set("estado servicio", estadoServicio);
    set("observaciones", limpiarObservacionesTecnicas(nuevaFila[bitCol.observaciones]));
    set("id calendar", idCalendar);
    set("referencia agenda", agendaRef);

    if (filaExistente > 0) {
      bitacora.getRange(filaExistente, 1, 1, headers.length).setValues([nuevaFila]);
    } else {
      bitacora.appendRow(nuevaFila);
    }

    return { ok: true };
  } catch (err) {
    Logger.log("Error sincronizando Bitacora: " + err.message);
    return { ok: false, error: err.message };
  }
}

function eliminarDeBitacora(row, sheet, col, idCalendar) {
  try {
    const { bitacora, data, bitCol } = prepararBitacora_();
    const valor = campo => {
      const c = col[normalizarTexto(campo)];
      return c ? sheet.getRange(row, c).getValue() : "";
    };
    const texto = campo => String(valor(campo) == null ? "" : valor(campo)).trim();
    const filaExistente = buscarFilaBitacora_(data, bitCol, {
      agendaRef: referenciaAgenda(sheet, row),
      idCalendar,
      fecha: valor("fecha"),
      clienta: texto("clienta"),
      servicio: estandarizarServicio(texto("servicio")),
    });

    if (filaExistente > 0) bitacora.deleteRow(filaExistente);
    return { ok: true, deleted: filaExistente > 0 };
  } catch (err) {
    Logger.log("Error eliminando de Bitacora: " + err.message);
    return { ok: false, error: err.message };
  }
}

function reconciliarMapeoBitacoraDesdeAgenda() {
  const agenda = SpreadsheetApp.getActiveSpreadsheet();
  if (!agenda) throw new Error("No se encontro la Agenda activa");

  const { bitacora, data, headers, bitCol } = prepararBitacora_();
  const eliminaciones = [];
  let actualizadas = 0;
  let sinCoincidencia = 0;

  obtenerFilasAgenda(agenda).forEach(origen => {
    const filaExistente = buscarFilaBitacora_(data, bitCol, {
      agendaRef: origen.agendaRef,
      idCalendar: origen.idCalendar,
      fecha: origen.fecha,
      clienta: origen.clienta,
      servicio: origen.servicio,
    });

    if (filaExistente < 0) {
      sinCoincidencia += 1;
      return;
    }
    if (estadoServicioDesdeAgenda(origen.estadoEvento, origen.fecha) === "Cancelado") {
      eliminaciones.push(filaExistente);
      return;
    }

    const fila = data[filaExistente - 1].slice(0, headers.length);
    fila[bitCol.direccion] = origen.direccion;
    fila[bitCol["id calendar"]] = origen.idCalendar;
    fila[bitCol["referencia agenda"]] = origen.agendaRef;
    fila[bitCol.observaciones] = limpiarObservacionesTecnicas(fila[bitCol.observaciones]);
    bitacora.getRange(filaExistente, 1, 1, headers.length).setValues([fila]);
    actualizadas += 1;
  });

  [...new Set(eliminaciones)].sort((a, b) => b - a).forEach(row => bitacora.deleteRow(row));
  Logger.log(JSON.stringify({
    actualizadas,
    canceladasEliminadas: [...new Set(eliminaciones)].length,
    sinCoincidencia,
  }));
}

function auditarReconciliacionBitacora() {
  reconciliarBitacoraDesdeAgenda_(true);
}

function auditarIdsCalendarDuplicados() {
  const agenda = SpreadsheetApp.getActiveSpreadsheet();
  const cal = CalendarApp.getCalendarById(CALENDAR_ID);
  const porId = {};

  obtenerFilasAgenda(agenda).forEach(origen => {
    if (!origen.idCalendar) return;
    if (!porId[origen.idCalendar]) porId[origen.idCalendar] = [];
    porId[origen.idCalendar].push(origen.agendaRef);
  });

  const duplicados = Object.keys(porId).filter(id => porId[id].length > 1);
  Logger.log("IDs Calendar duplicados: " + duplicados.length);
  if (!cal) {
    Logger.log("La cuenta actual no tiene acceso al calendario configurado.");
    duplicados.forEach(id => Logger.log(JSON.stringify({ id, filas: porId[id] })));
    return;
  }

  duplicados.forEach(id => {
    const ev = cal.getEventById(id);
    Logger.log(JSON.stringify({
      id,
      filas: porId[id],
      evento: ev ? {
        titulo: ev.getTitle(),
        inicio: Utilities.formatDate(ev.getStartTime(), "America/Santiago", "yyyy-MM-dd HH:mm"),
      } : null,
    }));
  });
}

function reconciliarBitacoraDesdeAgenda() {
  reconciliarBitacoraDesdeAgenda_(false);
}

function reconciliarBitacoraDesdeAgenda_(soloAuditar) {
  const agenda = SpreadsheetApp.getActiveSpreadsheet();
  if (!agenda) throw new Error("No se encontro la Agenda activa");

  const {
    bitacora,
    data: dataBitacora,
    headers: headersBitacora,
    bitCol,
  } = prepararBitacora_();

  const filasAgenda = obtenerFilasAgenda(agenda);
  const indiceAgenda = {};
  const indiceCalendar = {};
  const indiceClave = {};

  for (let i = 1; i < dataBitacora.length; i++) {
    const observaciones = String(dataBitacora[i][bitCol.observaciones] || "");
    const agendaMatch = observaciones.match(/Agenda:\s*([^|]+)/);
    const calendarMatch = observaciones.match(/Calendar:\s*([^|\s]+)/);
    const agendaRef = String(dataBitacora[i][bitCol["referencia agenda"]] || "").trim();
    const idCalendar = String(dataBitacora[i][bitCol["id calendar"]] || "").trim();
    if (agendaRef) indiceAgenda[agendaRef.replace("Agenda: ", "")] = i + 1;
    if (idCalendar) indiceCalendar[idCalendar] = i + 1;
    if (agendaMatch) indiceAgenda[agendaMatch[1].trim()] = i + 1;
    if (calendarMatch) indiceCalendar[calendarMatch[1].trim()] = i + 1;

    const clave = claveRegistro(
      dataBitacora[i][bitCol.fecha],
      dataBitacora[i][bitCol.clienta],
      dataBitacora[i][bitCol.servicio]
    );
    if (!indiceClave[clave]) indiceClave[clave] = [];
    indiceClave[clave].push(i + 1);
  }

  const lastRow = bitacora.getLastRow();
  const ids = lastRow > 1 ? bitacora.getRange(2, 1, lastRow - 1, 1).getValues().flat() : [];
  let maxId = ids.reduce((max, id) => {
    const n = parseInt(String(id).replace("QS-", ""), 10);
    return isNaN(n) ? max : Math.max(max, n);
  }, 0);

  const actualizaciones = [];
  const nuevasFilas = [];
  const conflictos = [];
  const incompletas = [];
  const cancelaciones = [];

  filasAgenda.forEach(origen => {
    const agendaKey = origen.agendaRef.replace("Agenda: ", "");
    const clave = claveRegistro(origen.fecha, origen.clienta, origen.servicio);
    const candidatas = indiceClave[clave] || [];
    const filaExistente = indiceAgenda[agendaKey]
      || (origen.idCalendar && indiceCalendar[origen.idCalendar])
      || (candidatas.length === 1 ? candidatas[0] : -1);

    if (candidatas.length > 1 && filaExistente < 0) {
      conflictos.push(origen.agendaRef + " -> coincidencias multiples: " + candidatas.join(", "));
    }

    if (estadoServicioDesdeAgenda(origen.estadoEvento, origen.fecha) === "Cancelado") {
      if (filaExistente > 0) cancelaciones.push(filaExistente);
      return;
    }

    const fila = filaExistente > 0
      ? dataBitacora[filaExistente - 1].slice(0, headersBitacora.length)
      : Array(headersBitacora.length).fill("");

    const set = (header, value) => {
      if (bitCol[normalizarTexto(header)] != null) fila[bitCol[normalizarTexto(header)]] = value;
    };

    if (filaExistente < 0) {
      maxId += 1;
      set("id", "QS-" + String(maxId).padStart(3, "0"));
    }

    set("fecha", fechaParaBitacora(origen.fecha));
    set("tipo", origen.tipo || "Servicio");
    set("encargada", origen.encargada);
    set("servicio", estandarizarServicio(origen.servicio));
    set("clienta", origen.clienta);
    set("dirección", origen.direccion);
    set("comuna", origen.comuna);
    set("traslado", estaVacio(origen.traslado) ? 0 : origen.traslado);
    set("abono", estaVacio(origen.abono) ? 0 : origen.abono);
    set("valor servicio", estaVacio(origen.valorServicio) ? 0 : origen.valorServicio);
    set("total servicio", estaVacio(origen.totalServicio) ? 0 : origen.totalServicio);
    set("saldo", estaVacio(origen.saldo) ? 0 : origen.saldo);
    set("forma de pago", fila[bitCol["forma de pago"]] || "Pendiente");
    set("estado pago", estadoPagoDesdeMontos(origen.abono, origen.saldo));
    set("estado servicio", estadoServicioDesdeAgenda(origen.estadoEvento, origen.fecha));
    set("observaciones", limpiarObservacionesTecnicas(fila[bitCol.observaciones]));
    set("id calendar", origen.idCalendar);
    set("referencia agenda", origen.agendaRef);

    if (origen.faltantes.length) incompletas.push(origen.agendaRef + " -> " + origen.faltantes.join(", "));
    if (filaExistente > 0) actualizaciones.push({ row: filaExistente, values: fila });
    else nuevasFilas.push(fila);
  });

  Logger.log(JSON.stringify({
    modo: soloAuditar ? "auditoria" : "aplicacion",
    filasAgenda: filasAgenda.length,
    filasBitacoraAntes: dataBitacora.length - 1,
    actualizar: actualizaciones.length,
    insertar: nuevasFilas.length,
    eliminarCanceladas: cancelaciones.length,
    incompletas: incompletas.length,
    conflictos: conflictos.length,
  }));
  if (incompletas.length) Logger.log("Filas incompletas: " + incompletas.slice(0, 30).join(" || "));
  if (conflictos.length) Logger.log("Conflictos: " + conflictos.slice(0, 30).join(" || "));

  if (soloAuditar) return;

  actualizaciones.forEach(item => {
    bitacora.getRange(item.row, 1, 1, headersBitacora.length).setValues([item.values]);
  });
  if (nuevasFilas.length) {
    bitacora.getRange(bitacora.getLastRow() + 1, 1, nuevasFilas.length, headersBitacora.length)
      .setValues(nuevasFilas);
  }
  [...new Set(cancelaciones)].sort((a, b) => b - a).forEach(row => bitacora.deleteRow(row));

  Logger.log("Reconciliacion completada correctamente.");
}

function obtenerFilasAgenda(agenda) {
  const omitidas = ["valores", "talleres"];
  const filas = [];

  agenda.getSheets().forEach(sheet => {
    if (omitidas.includes(normalizarTexto(sheet.getName()))) return;

    const data = sheet.getDataRange().getValues();
    if (!data.length) return;

    const headers = data[0].map(normalizarTexto);
    const col = {};
    headers.forEach((header, index) => {
      if (header) col[header] = index;
    });
    if (col.fecha == null || col.servicio == null || col.encargada == null) return;

    const valor = (row, campo) => {
      const index = col[normalizarTexto(campo)];
      return index == null ? "" : row[index];
    };
    const texto = (row, campo) => String(valor(row, campo) == null ? "" : valor(row, campo)).trim();

    for (let i = 1; i < data.length; i++) {
      const row = data[i];
      const fecha = valor(row, "fecha");
      const servicio = texto(row, "servicio");
      const encargada = texto(row, "encargada");
      if (estaVacio(fecha) || !servicio || !encargada) continue;

      const faltantes = CAMPOS_BITACORA.filter(campo => estaVacio(valor(row, campo)));
      filas.push({
        agendaRef: referenciaAgenda(sheet, i + 1),
        fecha,
        tipo: texto(row, "dia"),
        encargada,
        servicio,
        clienta: texto(row, "clienta"),
        direccion: texto(row, "direccion"),
        comuna: texto(row, "comuna"),
        traslado: valor(row, "traslado"),
        abono: valor(row, "abono"),
        valorServicio: valor(row, "valor servicio"),
        totalServicio: valor(row, "total servicio"),
        saldo: valor(row, "total por pagar"),
        estadoEvento: texto(row, "estado evento"),
        idCalendar: texto(row, "id evento"),
        faltantes,
      });
    }
  });

  return filas;
}

function estadoServicioDesdeAgenda(estadoEvento, fecha) {
  if (normalizarTexto(estadoEvento).includes("cancel")) return "Cancelado";

  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);
  const fechaServicio = fecha instanceof Date ? new Date(fecha) : new Date(fecha);
  fechaServicio.setHours(0, 0, 0, 0);
  return fechaServicio < hoy ? "Ejecutado" : "Pendiente";
}

function estadoPagoDesdeMontos(abono, saldo) {
  const abonoNumero = Number(abono) || 0;
  const saldoNumero = Number(saldo) || 0;
  if (saldoNumero <= 0 && abonoNumero > 0) return "Pagado";
  if (abonoNumero > 0) return "Parcial";
  return "Pendiente";
}

function referenciaAgenda(sheet, row) {
  return "Agenda: " + sheet.getName() + "!" + row;
}

function claveRegistro(fecha, clienta, servicio) {
  return [fechaClave(fecha), normalizarTexto(clienta), normalizarTexto(estandarizarServicio(servicio))].join("|");
}

function claveRegistroClientaFecha_(fecha, clienta) {
  return [fechaClave(fecha), normalizarTexto(clienta)].join("|");
}

function fechaClave(fecha) {
  if (fecha instanceof Date && !isNaN(fecha.getTime())) {
    return Utilities.formatDate(fecha, "America/Santiago", "yyyy-MM-dd");
  }
  return normalizarTexto(fecha);
}

function fechaParaBitacora(fecha) {
  if (!(fecha instanceof Date) || isNaN(fecha.getTime())) return fecha;
  const [year, month, day] = fechaClave(fecha).split("-").map(Number);
  return new Date(Date.UTC(year, month - 1, day, 12, 0, 0));
}

function limpiarObservacionesTecnicas(observaciones) {
  return String(observaciones || "")
    .replace(/(?:^|\s*\|\s*)(?:Calendar|Agenda|Legacy):\s*[^|]+/gi, "")
    .split("|")
    .map(texto => texto.trim())
    .filter(Boolean)
    .join(" | ");
}

function actualizarObservaciones(observaciones) {
  return limpiarObservacionesTecnicas(observaciones);
}

function estandarizarServicio(servicio) {
  const original = String(servicio || "").trim();
  if (!original || original.includes(":")) return original;

  const normalizado = normalizarTexto(original)
    .replace(/\s+/g, " ");
  if (normalizado.includes("novia civil") && normalizado.includes("reunion online")) {
    return "Novia Civil: M+P + Reunión Online";
  }
  if (normalizado.includes("novia civil")
      && normalizado.includes("prueba")
      && normalizado.includes("peinado")
      && normalizado.includes("maquillaje")) {
    return "Novia Civil: M+P + Prueba M+P Presencial";
  }
  if (normalizado.includes("novia fiesta") && normalizado.includes("prueba maquillaje presencial")) {
    return "Novia Fiesta: M+P + Prueba Maquillaje Presencial";
  }

  const equivalencias = {
    "maquillaje social + peinado": "Social: M+P",
    "maquillaje social - peinado": "Social: M+P",
    "social m+p": "Social: M+P",
    "maquillaje peinado social": "Social: M+P",
    "social maquillaje + peinado": "Social: M+P",
    "maquillaje social": "Social: Maquillaje",
    "social maquillaje": "Social: Maquillaje",
    "peinado social": "Social: Peinado",
    "peinados social": "Social: Peinado",
    "social peinado": "Social: Peinado",
    "peinado novia": "Novia: Peinado",
    "peinados novia": "Novia: Peinado",
    "peinado novia + prueba": "Novia: Peinado + Prueba",
    "maquillaje - peinado novia civil": "Novia Civil: M+P",
    "maquillaje peinado novia civil": "Novia Civil: M+P",
    "prueba de maquillaje online": "Prueba: Maquillaje (Online)",
    "prueba maquillaje online": "Prueba: Maquillaje (Online)",
    "prueba maquillaje y peinado": "Prueba: M+P",
    "peinado graduacion": "Graduación: Peinado",
    "glitterbar": "Evento: Glitter Bar",
    "glitter bar": "Evento: Glitter Bar",
    "manicure": "Manicure: Servicio",
    "maniqure": "Manicure: Servicio",
  };

  return equivalencias[normalizado] || original;
}

function validarCampos(campos, valor) {
  return campos.filter(campo => estaVacio(valor(campo)));
}

function estaVacio(valor) {
  return valor == null || (typeof valor === "string" && valor.trim() === "");
}

function valorDescripcion(valor) {
  if (typeof valor === "number") return String(valor);
  return String(valor == null ? "" : valor).trim();
}

function mapearEncabezados(sheet) {
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  const col = {};
  headers.forEach((header, index) => {
    const normalizado = normalizarTexto(header);
    if (normalizado) col[normalizado] = index + 1;
  });
  return col;
}

function normalizarTexto(valor) {
  return String(valor == null ? "" : valor)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .toLowerCase();
}

function limpiarNotaQS(nota) {
  return String(nota || "")
    .split("\n")
    .filter(linea => !linea.startsWith(NOTE_PREFIX))
    .join("\n")
    .trim();
}

function construirFechaHora(fechaValue, horaValue) {
  let fecha;

  if (fechaValue instanceof Date) {
    fecha = new Date(fechaValue);
  } else {
    const textoFecha = String(fechaValue || "").trim();
    const matchFecha = textoFecha.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);
    fecha = matchFecha
      ? new Date(Number(matchFecha[3]), Number(matchFecha[2]) - 1, Number(matchFecha[1]))
      : new Date(textoFecha);
  }

  if (isNaN(fecha.getTime())) throw new Error("Fecha invalida");

  let horas;
  let minutos;

  if (horaValue instanceof Date) {
    horas = horaValue.getHours();
    minutos = horaValue.getMinutes();
  } else if (typeof horaValue === "number") {
    if (Number.isInteger(horaValue) && horaValue >= 0 && horaValue < 24) {
      horas = horaValue;
      minutos = 0;
    } else {
      const totalMinutos = Math.round((((horaValue % 1) + 1) % 1) * 24 * 60);
      horas = Math.floor(totalMinutos / 60) % 24;
      minutos = totalMinutos % 60;
    }
  } else {
    const textoHora = String(horaValue || "").trim();
    const matchHora = textoHora.match(/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/i);
    if (!matchHora) throw new Error("Hora invalida");
    horas = Number(matchHora[1]);
    minutos = Number(matchHora[2] || 0);

    if (matchHora[3]) {
      const periodo = matchHora[3].toLowerCase();
      if (periodo === "pm" && horas < 12) horas += 12;
      if (periodo === "am" && horas === 12) horas = 0;
    }
  }

  if (horas < 0 || horas > 23 || minutos < 0 || minutos > 59) throw new Error("Hora invalida");
  fecha.setHours(horas, minutos, 0, 0);
  return fecha;
}

function probarConfiguracion() {
  const pruebasInvitados = [
    {
      encargada: "Paz",
      esperados: [ENCARGADA_EMAILS.paz],
    },
    {
      encargada: "Mou",
      esperados: [ENCARGADA_EMAILS.mou],
    },
    {
      encargada: "Cami - Paz",
      esperados: [ENCARGADA_EMAILS.paz, ENCARGADA_EMAILS.cami],
    },
  ];

  pruebasInvitados.forEach(prueba => {
    const actuales = obtenerInvitados(prueba.encargada);
    if (actuales.join(",") !== prueba.esperados.join(",")) {
      throw new Error("Invitados incorrectos para " + prueba.encargada + ": " + actuales.join(","));
    }
  });

  const fechaHora = construirFechaHora(new Date(2026, 4, 27), 7 / 24);
  if (fechaHora.getHours() !== 7 || fechaHora.getMinutes() !== 0) {
    throw new Error("Conversion de hora incorrecta: " + fechaHora);
  }

  Logger.log("Pruebas de configuracion completadas correctamente.");
}

function instalarTrigger() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  if (!ss) throw new Error("No se encontro la planilla activa");
  if (!CalendarApp.getCalendarById(CALENDAR_ID)) {
    throw new Error("La cuenta actual no tiene acceso al calendario configurado. Instalar el trigger desde la cuenta propietaria.");
  }

  ScriptApp.getProjectTriggers().forEach(trigger => {
    if (trigger.getHandlerFunction() === "crearEvento") ScriptApp.deleteTrigger(trigger);
  });

  ScriptApp.newTrigger("crearEvento")
    .forSpreadsheet(ss)
    .onEdit()
    .create();

  Logger.log("Trigger instalado correctamente.");
}

function ejecutarPruebaE2E() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getActiveSheet();
  
  Logger.log("=== INICIANDO PRUEBA END-TO-END (E2E) ===");
  
  // 1. Mapear encabezados de la hoja activa
  const col = mapearEncabezados(sheet);
  const camposRequeridos = ["encargada", "fecha", "hora", "servicio", "clienta", "telefono", "direccion", "comuna", "traslado", "abono", "valor servicio", "total servicio", "total por pagar", "accion", "estado evento", "id evento"];
  
  for (const campo of camposRequeridos) {
    if (!col[campo]) {
      Logger.log("Error E2E: Falta la columna '" + campo + "' en la hoja activa. Asegúrate de estar en la pestaña correcta.");
      return;
    }
  }
  
  // 2. Crear fila de prueba al final de la hoja principal
  const testRowIndex = sheet.getLastRow() + 1;
  Logger.log("Insertando fila de prueba en fila: " + testRowIndex);
  
  const testDate = new Date();
  testDate.setHours(0, 0, 0, 0); // Limpiar horas para consistencia
  
  sheet.getRange(testRowIndex, col["encargada"]).setValue("cami");
  sheet.getRange(testRowIndex, col["fecha"]).setValue(testDate);
  sheet.getRange(testRowIndex, col["hora"]).setValue("15:00");
  sheet.getRange(testRowIndex, col["servicio"]).setValue("Social Maquillaje");
  sheet.getRange(testRowIndex, col["clienta"]).setValue("PRUEBA E2E CLIENTA");
  sheet.getRange(testRowIndex, col["telefono"]).setValue("+56900000000");
  sheet.getRange(testRowIndex, col["direccion"]).setValue("Calle Prueba 123");
  sheet.getRange(testRowIndex, col["comuna"]).setValue("Providencia");
  sheet.getRange(testRowIndex, col["traslado"]).setValue(5000);
  sheet.getRange(testRowIndex, col["abono"]).setValue(10000);
  sheet.getRange(testRowIndex, col["valor servicio"]).setValue(45000);
  sheet.getRange(testRowIndex, col["total servicio"]).setValue(50000);
  sheet.getRange(testRowIndex, col["total por pagar"]).setValue(40000);
  sheet.getRange(testRowIndex, col["accion"]).setValue("CREAR");
  sheet.getRange(testRowIndex, col["estado evento"]).setValue("Pendiente");
  sheet.getRange(testRowIndex, col["id evento"]).setValue("");
  
  if (col["dia"]) {
    sheet.getRange(testRowIndex, col["dia"]).setValue("Sábado");
  }
  
  SpreadsheetApp.flush();
  Utilities.sleep(1000);
  
  // 3. Simular trigger onEdit de creación
  Logger.log("Simulando trigger de edición (CREAR)...");
  const mockRange = sheet.getRange(testRowIndex, col["accion"]);
  const mockEvent = {
    range: mockRange,
    value: "CREAR"
  };
  
  try {
    crearEvento(mockEvent);
    SpreadsheetApp.flush();
    Utilities.sleep(2000);
    
    // Verificar si se creó el evento en Calendar
    const idEvento = String(sheet.getRange(testRowIndex, col["id evento"]).getValue()).trim();
    const estadoEvento = String(sheet.getRange(testRowIndex, col["estado evento"]).getValue()).trim();
    
    Logger.log("Estado tras creación: " + estadoEvento + " | ID Evento: " + idEvento);
    
    if (!idEvento) {
      throw new Error("El ID Evento no fue generado.");
    }
    
    // Verificar si se sincronizó con Bitácora
    Logger.log("Verificando existencia en Bitácora QS...");
    const { bitacora, data, bitCol } = prepararBitacora_();
    const ref = referenciaAgenda(sheet, testRowIndex);
    const filaBitacora = buscarFilaBitacora_(data, bitCol, {
      agendaRef: ref,
      idCalendar: idEvento,
      fecha: testDate,
      clienta: "PRUEBA E2E CLIENTA",
      servicio: "Social: Maquillaje"
    });
    
    if (filaBitacora === -1) {
      throw new Error("La fila de prueba no fue encontrada en la Bitácora QS.");
    }
    Logger.log("Fila de prueba encontrada en Bitácora QS (fila " + filaBitacora + "). ¡Sincronización E2E exitosa!");
    
    // 4. Limpieza (CANCELAR)
    Logger.log("=== INICIANDO LIMPIEZA DE RASTROS ===");
    sheet.getRange(testRowIndex, col["accion"]).setValue("CANCELAR");
    SpreadsheetApp.flush();
    Utilities.sleep(1000);
    
    const mockCancelEvent = {
      range: sheet.getRange(testRowIndex, col["accion"]),
      value: "CANCELAR"
    };
    
    crearEvento(mockCancelEvent);
    SpreadsheetApp.flush();
    Utilities.sleep(2000);
    
    // Verificar que se eliminó de Bitácora
    const { data: dataPostCleanup } = prepararBitacora_();
    const filaBitacoraPostCleanup = buscarFilaBitacora_(dataPostCleanup, bitCol, {
      agendaRef: ref,
      idCalendar: idEvento,
      fecha: testDate,
      clienta: "PRUEBA E2E CLIENTA",
      servicio: "Social: Maquillaje"
    });
    
    if (filaBitacoraPostCleanup !== -1) {
      Logger.log("Advertencia: No se eliminó automáticamente de Bitácora. Forzando eliminación...");
      eliminarDeBitacora(testRowIndex, sheet, col, idEvento);
    } else {
      Logger.log("✓ Fila de prueba eliminada exitosamente de Bitácora QS.");
    }
    
  } catch (error) {
    Logger.log("❌ Error durante la prueba E2E: " + error.message);
  } finally {
    // 5. Eliminar fila de prueba de la hoja principal
    Logger.log("Eliminando fila de prueba de la hoja activa...");
    sheet.deleteRow(testRowIndex);
    SpreadsheetApp.flush();
    Logger.log("=== PRUEBA E2E FINALIZADA ===");
  }
}

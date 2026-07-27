
import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';
import { escapeHtml, money, badge, dash, toDateTimeLocal } from '../ui/formatting.js';
import { notify } from '../ui/notifications.js';
import { clearFormErrors } from '../ui/validation.js';
import { setTab } from './dashboard.js';
import { buildBitacoraImage } from './bitacora-image.js';

export async function loadBitacoras() {
  const data = await api('/api/v1/bitacoras');
  state.bitacoras = data.bitacoras;
  renderBitacoras();
}

function staffName(id) {
  if (!id) return null;
  const person = state.staff.find((item) => item.id === id);
  return person ? person.display_name : `#${id}`;
}

export function renderBitacoras() {
  const rows = state.bitacoras;
  $('#bitacoras-empty').style.display = rows.length ? 'none' : 'block';
  $('#bitacoras-body').innerHTML = rows.map((bitacora) => {
    const team = [staffName(bitacora.mua_id), staffName(bitacora.estilista_id)].filter(Boolean).join(' + ');
    const route = bitacora.route_plan || {};
    const travelWarn = route.recommended_minimum_met === false;
    return `
      <tr>
        <td>${bitacora.id}</td>
        <td>${escapeHtml(bitacora.fecha_servicio)}</td>
        <td class="wrap">${escapeHtml(bitacora.tipo_servicio)}</td>
        <td class="wrap">${escapeHtml(bitacora.clienta_nombre)}</td>
        <td class="wrap">${escapeHtml(dash(team))}</td>
        <td>${badge(`${route.travel_duration_min ?? 0} min`, travelWarn ? 'warn' : 'muted')}</td>
        <td>${money(bitacora.precio_cliente_clp)}</td>
        <td>${badge(money(bitacora.projected_margin_clp), bitacora.projected_margin_clp > 0 ? 'ok' : 'warn')}</td>
        <td>${bitacora.notes.length ? badge(String(bitacora.notes.length), 'muted') : '—'}</td>
        <td><button class="secondary btn-sm" type="button" data-edit-bitacora="${bitacora.id}">Editar</button></td>
      </tr>
    `;
  }).join('');
}

export function fillBitacoraStaffSelects() {
  const options = '<option value="">Sin asignar</option>' + state.staff
    .filter((person) => person.active)
    .map((person) => `<option value="${person.id}">${escapeHtml(person.display_name)}</option>`)
    .join('');
  $('#bitacora-mua-select').innerHTML = options;
  $('#bitacora-estilista-select').innerHTML = options;
}

export function resetBitacoraForm() {
  const form = $('#bitacora-form');
  form.reset();
  clearFormErrors(form);
  form.querySelector('[name=id]').value = '';
  form.querySelector('[name=booking_id]').value = '';
  $('#bitacora-form-title').textContent = 'Nueva bitácora';
  renderBookingLink(null);
  $('#bitacora-notes-panel').classList.add('hidden');
  $('#bitacora-notes-list').innerHTML = '';
  $('#tramos-list').innerHTML = '';
  const plan = $('#bitacora-plan');
  plan.classList.add('hidden');
  plan.innerHTML = '';
  const preview = $('#bitacora-team-preview');
  preview.classList.add('hidden');
  preview.textContent = '';
}

function renderBookingLink(bookingId) {
  const chip = $('#bitacora-booking-link');
  if (!bookingId) {
    chip.classList.add('hidden');
    chip.innerHTML = '';
    return;
  }

  chip.classList.remove('hidden');
  chip.innerHTML = `<button type="button" class="secondary btn-sm" data-bitacora-booking-link="${bookingId}">Vinculada a reserva #${bookingId}</button>`;
}

function renderNotes(bitacora) {
  $('#bitacora-notes-list').innerHTML = bitacora.notes.length
    ? bitacora.notes.map((note) => `<li>${escapeHtml(note.message)}<small>${escapeHtml(note.created_at)}</small></li>`).join('')
    : '<li class="empty-note">Sin notas todavía.</li>';
}

export function editBitacora(id) {
  const bitacora = state.bitacoras.find((item) => item.id === id);
  if (!bitacora) return;
  const form = $('#bitacora-form');
  clearFormErrors(form);
  const fields = form.elements;
  const route = bitacora.route_plan || {};
  fields.id.value = bitacora.id;
  fields.booking_id.value = bitacora.booking_id || '';
  fields.fecha_servicio.value = bitacora.fecha_servicio;
  fields.tipo_servicio.value = bitacora.tipo_servicio;
  fields.clienta_nombre.value = bitacora.clienta_nombre;
  fields.mua_id.value = bitacora.mua_id || '';
  fields.estilista_id.value = bitacora.estilista_id || '';
  fields.direccion_servicio.value = bitacora.direccion_servicio;
  fields.punto_salida.value = route.pickup_point || '';
  fields.orden_recogida.value = route.pickup_order || '';
  fields.tiempo_traslado_min.value = route.travel_duration_min ?? 0;
  fields.hora_llegada.value = route.arrival_time || '';
  fields.costo_staff_clp.value = bitacora.costo_staff_clp;
  fields.precio_cliente_clp.value = bitacora.precio_cliente_clp;
  fields.notas_logisticas.value = bitacora.notas_logisticas || '';
  fields.hora_inicio_servicio.value = bitacora.hora_inicio_servicio || '';
  fields.hora_fin_servicio.value = bitacora.hora_fin_servicio || '';
  fields.objetivo.value = bitacora.objetivo || '';
  fields.consideraciones.value = bitacora.consideraciones || '';
  renderTramos(bitacora.tramos);
  $('#bitacora-form-title').textContent = `Bitácora #${bitacora.id}`;
  renderBookingLink(bitacora.booking_id);
  $('#bitacora-notes-panel').classList.remove('hidden');
  renderNotes(bitacora);
  updateBitacoraPlan();
}

export function startBitacoraFromBooking(booking) {
  if (!booking) return;
  setTab('bitacora');
  resetBitacoraForm();

  const form = $('#bitacora-form');
  const fields = form.elements;
  const addressParts = [booking.address, booking.comuna].filter(Boolean);
  const staff = state.staff.find((person) => person.active && person.id === booking.staff_id);

  fields.booking_id.value = booking.id;
  fields.fecha_servicio.value = toDateTimeLocal(booking.scheduled_for).slice(0, 10);
  fields.clienta_nombre.value = booking.customer_name || '';
  fields.direccion_servicio.value = addressParts.join(', ');
  fields.tipo_servicio.value = booking.service_name || '';
  fields.precio_cliente_clp.value = booking.total_service ?? 0;
  fields.mua_id.value = staff ? staff.id : '';
  // La planilla registra las dos profesionales; la reserva ahora guarda ambas.
  if (booking.estilista_id) fields.estilista_id.value = booking.estilista_id;

  // Punto de salida habitual del estudio, editable si ese dia sale de otro lado.
  fields.punto_salida.value = PUNTO_SALIDA_HABITUAL;

  // Horario del servicio: inicio desde la reserva (hora local); fin
  // sumando la duracion del servicio del catalogo cuando se conoce.
  const inicio = toDateTimeLocal(booking.scheduled_for).slice(11, 16);
  fields.hora_inicio_servicio.value = inicio;
  const service = state.services.find((item) => item.id === booking.service_id);
  if (inicio && service && service.duration_minutes) {
    fields.hora_fin_servicio.value = timeMinus(inicio, -service.duration_minutes);
  }

  renderBookingLink(booking.id);
  updateBitacoraPlan();
}

// Regla operativa (misma que TravelPlanCalculator en el backend): llegar
// 15 min antes del inicio, y sumar 15 min de holgura por trafico diluidos
// en el viaje.
const ARRIVAL_BUFFER_MIN = 15;
const SLACK_MIN = 15;
// Punto de salida habitual (alternativa conocida: estudio Huérfanos 1044).
const PUNTO_SALIDA_HABITUAL = 'Metro Macul';

function timeMinus(hhmm, minutes) {
  const match = /^(\d{1,2}):(\d{2})/.exec(hhmm || '');
  if (!match) return null;
  let total = Number(match[1]) * 60 + Number(match[2]) - minutes;
  total = ((total % 1440) + 1440) % 1440;
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(Math.floor(total / 60))}:${pad(total % 60)}`;
}

function tramoRowHtml(tramo = {}) {
  return `
    <div class="tramo-row" data-tramo>
      <input class="tramo-nombre" placeholder="Ej: Metro Macul → Providencia" maxlength="160" value="${escapeHtml(String(tramo.nombre ?? ''))}">
      <input class="tramo-min" type="number" min="0" step="1" placeholder="min" value="${escapeHtml(String(tramo.minutos ?? ''))}">
      <input class="tramo-recoge" placeholder="recoge a…" maxlength="80" value="${escapeHtml(String(tramo.recoge ?? ''))}">
      <input class="tramo-comuna" placeholder="comuna" maxlength="80" value="${escapeHtml(String(tramo.comuna ?? ''))}">
      <button type="button" class="secondary btn-sm tramo-remove" data-remove-tramo title="Quitar tramo">✕</button>
    </div>
  `;
}

export function addTramoRow(tramo = {}) {
  $('#tramos-list').insertAdjacentHTML('beforeend', tramoRowHtml(tramo));
}

function renderTramos(tramos) {
  $('#tramos-list').innerHTML = (tramos || []).map((t) => tramoRowHtml(t)).join('');
}

function collectTramos() {
  return Array.from(document.querySelectorAll('#tramos-list [data-tramo]'))
    .map((row) => {
      const tramo = {
        nombre: row.querySelector('.tramo-nombre').value.trim(),
        minutos: Number(row.querySelector('.tramo-min').value || 0),
      };
      const recoge = row.querySelector('.tramo-recoge').value.trim();
      const comuna = row.querySelector('.tramo-comuna').value.trim();
      if (recoge) tramo.recoge = recoge;
      if (comuna) tramo.comuna = comuna;
      return tramo;
    })
    .filter((t) => t.nombre !== '');
}

/**
 * Horas de recogida: mismo esquema que el backend (TravelPlanCalculator),
 * con la holgura repartida proporcionalmente a lo recorrido.
 */
function pickupSchedule(inicio, tramos) {
  const total = tramos.reduce((sum, t) => sum + t.minutos, 0);
  if (!inicio || !total) return [];

  const salida = timeMinus(inicio, ARRIVAL_BUFFER_MIN + total + SLACK_MIN);
  const factor = (total + SLACK_MIN) / total;
  let elapsed = 0;

  return tramos.reduce((acc, tramo) => {
    elapsed += tramo.minutos;
    if (tramo.recoge) {
      acc.push({
        label: tramo.comuna ? `${tramo.recoge} (${tramo.comuna})` : tramo.recoge,
        hora: timeMinus(salida, -Math.round(elapsed * factor)),
      });
    }
    return acc;
  }, []);
}

export function updateBitacoraPlan() {
  const box = $('#bitacora-plan');
  if (!box) return;
  const fields = $('#bitacora-form').elements;
  const tramos = collectTramos();

  const faltantes = [];
  if (!fields.clienta_nombre.value.trim()) faltantes.push('clienta');
  if (!fields.direccion_servicio.value.trim()) faltantes.push('dirección del servicio');
  if (!fields.hora_inicio_servicio.value) faltantes.push('hora de inicio del servicio');
  if (!tramos.length) faltantes.push('al menos un tramo con su tiempo');

  box.classList.remove('hidden');
  const inicio = fields.hora_inicio_servicio.value;
  const totalTramos = tramos.reduce((sum, t) => sum + t.minutos, 0);
  const llegada = inicio ? timeMinus(inicio, ARRIVAL_BUFFER_MIN) : null;
  const salida = inicio && tramos.length
    ? timeMinus(inicio, ARRIVAL_BUFFER_MIN + totalTramos + SLACK_MIN)
    : null;

  if (faltantes.length) {
    box.className = 'availability-hint full warn';
    box.innerHTML = `<strong>⚠️ Falta para armar la bitácora:</strong> ${escapeHtml(faltantes.join(', '))}.`
      + (llegada ? `<span>🕒 Llegada objetivo: ${llegada} hrs (15 min antes del inicio).</span>` : '');
    return;
  }

  const recogidas = pickupSchedule(inicio, tramos);
  box.className = 'availability-hint full ok';
  box.innerHTML = `<strong>✅ Plan de traslado completo.</strong>`
    + `<span>🕐 Salida sugerida: <strong>${salida} hrs</strong> · 🕒 Llegada: <strong>${llegada} hrs</strong> (15 min antes del inicio, holgura de ${SLACK_MIN} min incluida en el viaje).</span>`
    + (recogidas.length
      ? `<span>🧍 Recogidas: ${recogidas.map((r) => `<strong>${escapeHtml(r.hora)}</strong> ${escapeHtml(r.label)}`).join(' · ')}</span>`
      : '');
}

function selectedName(select) {
  const option = select.selectedOptions[0];
  return select.value && option ? option.textContent.trim() : null;
}

// Extraccion unica de los datos de la bitacora: la usan tanto el texto
// copiable como la imagen, para que no puedan divergir.
function bitacoraFields() {
  const fields = $('#bitacora-form').elements;
  const tramos = collectTramos();
  const inicio = fields.hora_inicio_servicio.value;
  const fin = fields.hora_fin_servicio.value;
  const totalTramos = tramos.reduce((sum, t) => sum + t.minutos, 0);
  const llegada = inicio ? timeMinus(inicio, ARRIVAL_BUFFER_MIN) : null;
  const salida = inicio && tramos.length
    ? timeMinus(inicio, ARRIVAL_BUFFER_MIN + totalTramos + SLACK_MIN)
    : null;

  const mua = selectedName(fields.mua_id);
  const estilista = selectedName(fields.estilista_id);

  return {
    tipo: fields.tipo_servicio.value.trim(),
    clienta: fields.clienta_nombre.value.trim(),
    fecha: fields.fecha_servicio.value,
    direccion: fields.direccion_servicio.value.trim(),
    puntoSalida: fields.punto_salida.value.trim(),
    ordenManual: fields.orden_recogida.value.trim(),
    objetivo: fields.objetivo.value.trim(),
    consideraciones: fields.consideraciones.value.trim(),
    notas: fields.notas_logisticas.value.trim(),
    inicio,
    fin,
    llegada,
    salida,
    tramos,
    totalTramos,
    profesionales: [
      mua ? `${mua} (maquilladora)` : null,
      estilista ? `${estilista} (estilista)` : null,
    ].filter(Boolean).join(', '),
  };
}

function fechaLarga(iso) {
  if (!iso) return '—';
  const date = new Date(`${iso}T12:00:00`);
  if (Number.isNaN(date.getTime())) return iso;
  const texto = date.toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' });
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/**
 * Filas del documento que se manda al equipo, en el orden acordado.
 */
export function bitacoraImageData() {
  const f = bitacoraFields();
  // Un tramo por linea: el nombre del tramo ya suele traer su propia flecha
  // ("Estudio -> Metro"), asi que unirlos con otra flecha se lee pesimo.
  const ruta = f.tramos.length
    ? f.tramos.map((t) => `${t.nombre} — ${t.minutos} min`).join('\n')
      + `\nTotal ${f.totalTramos} min + ${SLACK_MIN} de holgura por tráfico`
    : '—';

  const filas = [
    { campo: '📅 Fecha', valor: fechaLarga(f.fecha) },
    { campo: '💄 Tipo de actividad', valor: f.tipo || '—' },
    { campo: '👰 Clienta', valor: f.clienta || '—' },
    { campo: '📍 Dirección del servicio', valor: f.direccion || '—' },
    { campo: '⏰ Horario del servicio', valor: f.inicio ? `${f.inicio}${f.fin ? ` - ${f.fin}` : ''} hrs` : '—' },
    { campo: '🧑‍🎨 Profesionales', valor: f.profesionales || '—' },
    { campo: '🚪 Punto de salida', valor: f.puntoSalida || '—' },
    { campo: '🗺️ Orden de traslado', valor: f.ordenManual || (f.tramos.length ? f.tramos.map((t) => t.nombre).join('  ·  ') : '—') },
    { campo: '🕐 Hora de salida', valor: f.salida ? `${f.salida} hrs` : '—' },
    { campo: '🚗 Ruta estimada', valor: ruta },
  ];

  // Las recogidas van justo despues de la salida: es lo que cada profesional
  // necesita saber. Solo comuna, nunca la direccion exacta.
  const recogidas = pickupSchedule(f.inicio, f.tramos);
  if (recogidas.length) {
    filas.splice(9, 0, {
      campo: '🧍 Horas de recogida',
      valor: recogidas.map((r) => `${r.hora} hrs — ${r.label}`).join('\n'),
    });
  }

  filas.splice(recogidas.length ? 10 : 9, 0, {
    campo: '🕒 Hora de llegada estimada',
    valor: f.llegada ? `${f.llegada} hrs (15 minutos antes del inicio)` : '—',
  });

  if (f.objetivo) filas.push({ campo: '🎯 Objetivo principal', valor: f.objetivo });
  if (f.consideraciones) filas.push({ campo: '📝 Consideraciones', valor: f.consideraciones });
  if (f.notas) filas.push({ campo: '🧭 Notas logísticas', valor: f.notas });

  return {
    titulo: `✨ Bitácora - ${f.tipo || 'Servicio'} - ${f.clienta || 'Sin clienta'}`,
    filas,
  };
}

function teamBitacoraText() {
  const fields = $('#bitacora-form').elements;
  const tramos = collectTramos();
  const inicio = fields.hora_inicio_servicio.value;
  const fin = fields.hora_fin_servicio.value;
  const totalTramos = tramos.reduce((sum, t) => sum + t.minutos, 0);
  const llegada = inicio ? timeMinus(inicio, ARRIVAL_BUFFER_MIN) : null;
  const salida = inicio && tramos.length
    ? timeMinus(inicio, ARRIVAL_BUFFER_MIN + totalTramos + SLACK_MIN)
    : null;

  const mua = selectedName(fields.mua_id);
  const estilista = selectedName(fields.estilista_id);
  const profesionales = [
    mua ? `${mua} (maquilladora)` : null,
    estilista ? `${estilista} (estilista)` : null,
  ].filter(Boolean).join(', ');

  const lines = [
    `✨ Bitácora — ${fields.tipo_servicio.value.trim() || '—'} — ${fields.clienta_nombre.value.trim() || '—'}`,
    '',
    `📅 Fecha: ${fields.fecha_servicio.value || '—'}`,
    `💄 Tipo de actividad: ${fields.tipo_servicio.value.trim() || '—'}`,
    `👰 Clienta: ${fields.clienta_nombre.value.trim() || '—'}`,
    `📍 Dirección del servicio: ${fields.direccion_servicio.value.trim() || '—'}`,
    `⏰ Horario del servicio: ${inicio || '—'}${fin ? ` - ${fin}` : ''} hrs`,
    `🧑‍🎨 Profesionales: ${profesionales || '—'}`,
    `🚪 Punto de salida: ${fields.punto_salida.value.trim() || '—'}`,
    `🗺️ Orden de traslado: ${tramos.map((t) => t.nombre).join(' → ') || fields.orden_recogida.value.trim() || '—'}`,
    `🕐 Hora de salida: ${salida ? `${salida} hrs` : '—'}`,
    `🕒 Hora de llegada estimada: ${llegada ? `${llegada} hrs (15 minutos antes del inicio)` : '—'}`,
  ];

  if (fields.objetivo.value.trim()) lines.push(`🎯 Objetivo principal: ${fields.objetivo.value.trim()}`);
  if (fields.consideraciones.value.trim()) lines.push(`📝 Consideraciones: ${fields.consideraciones.value.trim()}`);
  if (fields.notas_logisticas.value.trim()) lines.push(`🧭 Notas logísticas: ${fields.notas_logisticas.value.trim()}`);

  return lines.join('\n');
}

export async function copyTeamBitacora() {
  const text = teamBitacoraText();
  const preview = $('#bitacora-team-preview');
  preview.textContent = text;
  preview.classList.remove('hidden');
  try {
    await navigator.clipboard.writeText(text);
    notify('📋 Bitácora copiada — lista para enviar al equipo.');
  } catch (error) {
    notify('No se pudo copiar automáticamente: usa la vista previa de abajo.', true);
  }
}

export function generateBitacoraImage() {
  const data = bitacoraImageData();
  const canvas = buildBitacoraImage(data);
  const holder = $('#bitacora-image-preview');

  canvas.style.width = '100%';
  canvas.style.borderRadius = '10px';
  canvas.style.border = '1px solid var(--border-light)';

  const link = document.createElement('a');
  link.className = 'secondary btn-sm';
  link.textContent = '⬇️ Descargar imagen';
  link.download = `bitacora-${$('#bitacora-form').elements.fecha_servicio.value || 'servicio'}.png`;
  link.href = canvas.toDataURL('image/png');

  holder.innerHTML = '';
  holder.appendChild(canvas);
  holder.appendChild(link);
  holder.classList.remove('hidden');
  notify('🖼️ Imagen generada: descárgala y mándala al equipo.');
}

export function bitacoraPayload() {
  const fields = $('#bitacora-form').elements;
  return {
    booking_id: fields.booking_id.value ? Number(fields.booking_id.value) : null,
    fecha_servicio: fields.fecha_servicio.value,
    tipo_servicio: fields.tipo_servicio.value.trim(),
    clienta_nombre: fields.clienta_nombre.value.trim(),
    mua_id: fields.mua_id.value ? Number(fields.mua_id.value) : null,
    estilista_id: fields.estilista_id.value ? Number(fields.estilista_id.value) : null,
    direccion_servicio: fields.direccion_servicio.value.trim(),
    punto_salida: fields.punto_salida.value.trim(),
    orden_recogida: fields.orden_recogida.value.trim() || null,
    tiempo_traslado_min: Number(fields.tiempo_traslado_min.value || 0),
    hora_llegada: fields.hora_llegada.value || null,
    costo_staff_clp: Number(fields.costo_staff_clp.value || 0),
    precio_cliente_clp: Number(fields.precio_cliente_clp.value || 0),
    notas_logisticas: fields.notas_logisticas.value.trim() || null,
    hora_inicio_servicio: fields.hora_inicio_servicio.value || null,
    hora_fin_servicio: fields.hora_fin_servicio.value || null,
    tramos: collectTramos(),
    objetivo: fields.objetivo.value.trim() || null,
    consideraciones: fields.consideraciones.value.trim() || null,
  };
}

export async function addBitacoraNote() {
  const id = $('#bitacora-form [name=id]').value;
  const input = $('#bitacora-note-message');
  const message = input.value.trim();
  if (!id || !message) return;
  try {
    const data = await api(`/api/v1/bitacoras/${id}/notes`, {
      method: 'POST',
      body: JSON.stringify({ message }),
    });
    input.value = '';
    const index = state.bitacoras.findIndex((item) => item.id === Number(id));
    if (index >= 0) state.bitacoras[index] = data.bitacora;
    renderNotes(data.bitacora);
    renderBitacoras();
    notify('Nota agregada.');
  } catch (error) {
    notify(error.message, true);
  }
}


import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';
import { escapeHtml, money, badge, dash } from '../ui/formatting.js';
import { notify } from '../ui/notifications.js';
import { clearFormErrors } from '../ui/validation.js';

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
  $('#bitacora-form-title').textContent = 'Nueva bitácora';
  $('#bitacora-notes-panel').classList.add('hidden');
  $('#bitacora-notes-list').innerHTML = '';
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
  $('#bitacora-form-title').textContent = `Bitácora #${bitacora.id}`;
  $('#bitacora-notes-panel').classList.remove('hidden');
  renderNotes(bitacora);
}

export function bitacoraPayload() {
  const fields = $('#bitacora-form').elements;
  return {
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

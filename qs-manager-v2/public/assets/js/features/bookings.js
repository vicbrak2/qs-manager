
import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';
import { escapeHtml, money, badge, sourceLabel, dash, formatDate, formatTime, text, toDateTimeLocal, fromDateTimeLocal, numberOrNull, idOrNull } from '../ui/formatting.js';
import { notify } from '../ui/notifications.js';
import { clearFormErrors, showFormErrors } from '../ui/validation.js';
import { refreshMetrics } from './dashboard.js';

const RECEIPT_MAX_SIDE = 1600;
const RECEIPT_QUALITY = 0.78;

export async function loadBookings() {
  const data = await api('/api/v1/bookings');
  state.bookings = data.bookings;
  renderBookings();
  refreshMetrics();
}

export async function completeBookingService(id) {
  const data = await api(`/api/v1/bookings/${id}/complete-service`, { method: 'POST' });
  await loadBookings();
  return data;
}

export async function loadStaff() {
  const data = await api('/api/v1/team');
  state.staff = data.staff;
  fillStaffSelect();
}

export function bookingMatches(booking) {
  const query = $('#booking-filter-text').value.trim().toLowerCase();
  const serviceId = $('#booking-filter-service').value;
  const staffId = $('#booking-filter-staff').value;
  const status = $('#booking-filter-status').value;
  const payment = $('#booking-filter-payment').value.trim().toLowerCase();
  
  const haystack = [booking.customer_name, booking.customer_phone, booking.service_name, booking.comuna, booking.address].join(' ').toLowerCase();
  if (query && !haystack.includes(query)) return false;
  if (serviceId && String(booking.service_id) !== serviceId) return false;
  if (staffId && String(booking.staff_id) !== staffId) return false;
  if (status && booking.status !== status) return false;
  if (payment && !text(booking.payment_status).toLowerCase().includes(payment)) return false;
  return true;
}

const numericSortKeys = new Set(['id', 'total_service', 'balance_due']);

function bookingSortValue(booking, key) {
  const value = booking[key];
  if (value === null || value === undefined || value === '') return null;
  if (key === 'scheduled_for') {
    const timestamp = Date.parse(value);
    return Number.isNaN(timestamp) ? null : timestamp;
  }
  if (numericSortKeys.has(key)) {
    const number = Number(value);
    return Number.isNaN(number) ? null : number;
  }
  return String(value).trim().toLocaleLowerCase('es-CL');
}

function compareBookings(left, right) {
  const { key, direction } = state.bookingsSort;
  const leftValue = bookingSortValue(left, key);
  const rightValue = bookingSortValue(right, key);

  if (leftValue === null && rightValue === null) return Number(left.id) - Number(right.id);
  if (leftValue === null) return 1;
  if (rightValue === null) return -1;

  let comparison;
  if (typeof leftValue === 'string' && typeof rightValue === 'string') {
    comparison = leftValue.localeCompare(rightValue, 'es-CL', { numeric: true, sensitivity: 'base' });
  } else {
    comparison = leftValue - rightValue;
  }

  if (comparison === 0) comparison = Number(left.id) - Number(right.id);
  return direction === 'asc' ? comparison : -comparison;
}

function isHistoricalBooking(booking, now = Date.now()) {
  return booking.status === 'completed' || booking.status === 'cancelled';
}

export function visibleBookings() {
  return state.bookings
    .filter(bookingMatches)
    .filter((booking) => state.bookingsView === 'history' ? isHistoricalBooking(booking) : !isHistoricalBooking(booking));
}

function updateBookingSortHeaders() {
  document.querySelectorAll('[data-booking-sort]').forEach((button) => {
    const active = button.dataset.bookingSort === state.bookingsSort.key;
    const header = button.closest('th');
    const indicator = button.querySelector('.sort-indicator');
    button.classList.toggle('active', active);
    header.removeAttribute('aria-sort');
    indicator.textContent = '';

    if (active) {
      const ascending = state.bookingsSort.direction === 'asc';
      header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
      indicator.textContent = ascending ? '▲' : '▼';
    }
  });
}

export function toggleBookingSort(key) {
  if (state.bookingsSort.key === key) {
    // Ya esta ordenado por esta columna: alternar direccion, sin importar
    // la columna (antes "scheduled_for" era un caso especial que siempre
    // reimponia la direccion segun la vista actual y nunca alternaba --
    // clickear la fecha de nuevo no hacia nada).
    state.bookingsSort.direction = state.bookingsSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    state.bookingsSort.key = key;
    state.bookingsSort.direction = key === 'scheduled_for'
      ? (state.bookingsView === 'upcoming' ? 'asc' : 'desc')
      : 'asc';
  }
  state.bookingsPagination.currentPage = 1;
  renderBookings();
}

export function setBookingsView(view) {
  state.bookingsView = view === 'history' ? 'history' : 'upcoming';
  state.bookingsSort.key = 'scheduled_for';
  state.bookingsSort.direction = state.bookingsView === 'upcoming' ? 'asc' : 'desc';
  state.bookingsPagination.currentPage = 1;
  renderBookings();
}

export function bookingTrafficKind(booking, now = Date.now()) {
  if (booking.status === 'cancelled') return 'cancelled';

  const scheduledAt = Date.parse(booking.scheduled_for);
  if (booking.status === 'completed') {
    return 'completed';
  }

  if (!Number.isNaN(scheduledAt) && scheduledAt <= now) {
    return 'overdue';
  }

  const sevenDaysMs = 7 * 24 * 60 * 60 * 1000;
  if (!Number.isNaN(scheduledAt) && scheduledAt - now <= sevenDaysMs) {
    return 'urgent';
  }

  return 'scheduled';
}

function receiptStatusText(booking = null) {
  if (booking?.has_transfer_receipt) {
    const sizeKb = booking.transfer_receipt_size ? Math.round(Number(booking.transfer_receipt_size) / 1024) : null;
    return `Comprobante guardado${sizeKb ? ` (${sizeKb} KB)` : ''}. Selecciona otro archivo para reemplazarlo.`;
  }

  return 'Opcional. La imagen se comprime antes de guardar.';
}

function readImage(file) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(file);
    image.onload = () => {
      URL.revokeObjectURL(url);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('No se pudo leer el comprobante seleccionado.'));
    };
    image.src = url;
  });
}

function canvasToDataUrl(canvas, mime, quality) {
  return canvas.toDataURL(mime, quality);
}

async function compressedTransferReceipt(file) {
  if (!file) return null;
  if (!file.type.startsWith('image/')) {
    throw new Error('El comprobante debe ser una imagen.');
  }

  const image = await readImage(file);
  const scale = Math.min(1, RECEIPT_MAX_SIDE / Math.max(image.naturalWidth, image.naturalHeight));
  const width = Math.max(1, Math.round(image.naturalWidth * scale));
  const height = Math.max(1, Math.round(image.naturalHeight * scale));
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext('2d');
  context.drawImage(image, 0, 0, width, height);

  let mime = 'image/webp';
  let dataUrl = canvasToDataUrl(canvas, mime, RECEIPT_QUALITY);
  if (!dataUrl.startsWith('data:image/webp')) {
    mime = 'image/jpeg';
    dataUrl = canvasToDataUrl(canvas, mime, RECEIPT_QUALITY);
  }

  const base64 = dataUrl.split(',')[1] || '';
  const approxBytes = Math.ceil(base64.length * 3 / 4);
  if (approxBytes > 450000) {
    dataUrl = canvasToDataUrl(canvas, mime, 0.62);
  }

  return {
    data_url: dataUrl,
    filename: file.name.replace(/\.[^.]+$/, '') + (mime === 'image/webp' ? '.webp' : '.jpg'),
  };
}

export function renderBookings() {
  const allFiltered = state.bookings.filter(bookingMatches);
  const upcomingCount = allFiltered.filter((booking) => !isHistoricalBooking(booking)).length;
  const historyCount = allFiltered.length - upcomingCount;
  $('#booking-upcoming-count').textContent = upcomingCount;
  $('#booking-history-count').textContent = historyCount;
  document.querySelectorAll('[data-booking-view]').forEach((button) => {
    const active = button.dataset.bookingView === state.bookingsView;
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
  });
  $('#booking-list-title').textContent = state.bookingsView === 'upcoming' ? 'Próximas reservas' : 'Historial';
  $('#booking-list-copy').textContent = state.bookingsView === 'upcoming'
    ? 'Ordenadas desde la atención pendiente más cercana.'
    : 'Servicios completados, fechas pasadas y reservas canceladas; lo más reciente aparece primero.';

  const filtered = visibleBookings().sort(compareBookings);
  $('#bookings-empty').style.display = filtered.length ? 'none' : 'block';
  updateBookingSortHeaders();
  
  const perPage = Number($('#booking-per-page').value);
  const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
  
  if (state.bookingsPagination.currentPage > totalPages) {
    state.bookingsPagination.currentPage = totalPages;
  }
  if (state.bookingsPagination.currentPage < 1) {
    state.bookingsPagination.currentPage = 1;
  }
  const currentPage = state.bookingsPagination.currentPage;
  
  const startIndex = (currentPage - 1) * perPage;
  const endIndex = startIndex + perPage;
  const rows = filtered.slice(startIndex, endIndex);

  $('#booking-page-indicator').textContent = `Página ${currentPage} de ${totalPages}`;
  $('#booking-prev-page').disabled = currentPage === 1;
  $('#booking-next-page').disabled = currentPage === totalPages;

  $('#bookings-body').innerHTML = rows.map((booking) => {
    const statusKind = booking.status === 'confirmed' ? 'ok' : booking.status === 'cancelled' ? 'danger' : booking.status === 'completed' ? 'warn' : 'muted';
    const trafficKind = bookingTrafficKind(booking);
    
    let syncStatusLabel = 'local';
    let syncStatusKind = 'muted';
    if (booking.gas_last_sync_status) {
      if (booking.gas_last_sync_status === 'synced' || booking.gas_last_sync_status === 'success') {
        syncStatusLabel = 'GAS: synced';
        syncStatusKind = 'ok';
      } else if (booking.gas_last_sync_status === 'failed') {
        syncStatusLabel = 'GAS: failed';
        syncStatusKind = 'danger';
      } else {
        syncStatusLabel = `GAS: ${booking.gas_last_sync_status}`;
        syncStatusKind = 'warn';
      }
    }

    const syncStatus = booking.gas_last_sync_status || 'not-synced';
    let syncStatusClass = 'not-synced';
    if (syncStatus === 'synced' || syncStatus === 'success') {
      syncStatusClass = 'success';
    } else if (syncStatus === 'failed') {
      syncStatusClass = 'failed';
    } else if (syncStatus === 'skipped') {
      syncStatusClass = 'skipped';
    } else if (syncStatus === 'pending' || syncStatus === 'not-synced') {
      syncStatusClass = 'pending';
    }
    // Sin bitácora y con el servicio encima: la fila se marca en rojo para
    // que no se pase la fecha sin haberla mandado al equipo.
    const bitacoraPendiente = !booking.bitacora_id && (trafficKind === 'urgent' || trafficKind === 'overdue');
    const bitacoraAction = booking.bitacora_id
      ? `<button class="secondary btn-sm" type="button" data-open-bitacora="${booking.bitacora_id}">Ver bitácora #${booking.bitacora_id}</button>`
      : `<button class="${bitacoraPendiente ? 'danger' : 'secondary'} btn-sm" type="button" data-create-bitacora="${booking.id}">${bitacoraPendiente ? '⚠ Falta bitácora' : 'Crear bitácora'}</button>`;
    const canCompleteService = booking.status !== 'completed' && booking.status !== 'cancelled';
    const completeServiceAction = canCompleteService
      ? `<button class="success btn-sm" type="button" data-complete-booking-service="${booking.id}" title="Libera el abono retenido al marcar el servicio como realizado">Terminar servicio</button>`
      : '';
    
    return `
      <tr class="booking-traffic-${trafficKind}${bitacoraPendiente ? ' booking-sin-bitacora' : ''}" data-booking-traffic="${trafficKind}"${bitacoraPendiente ? ' data-bitacora-pendiente="true"' : ''}>
        <td>${booking.id}</td>
        <td>${formatDate(booking.scheduled_for)}</td>
        <td>${formatTime(booking.scheduled_for)}</td>
        <td class="wrap">${escapeHtml(booking.customer_name || '')}</td>
        <td>${escapeHtml(dash(booking.customer_phone))}</td>
        <td class="wrap">${escapeHtml(booking.service_name || '')}</td>
        <td>${escapeHtml(dash(booking.comuna))}</td>
        <td class="wrap">${escapeHtml(dash(booking.address))}</td>
        <td>${money(booking.total_service)}</td>
        <td>${money(booking.balance_due)}</td>
        <td>${booking.payment_status ? badge(booking.payment_status, booking.payment_status.toLowerCase() === 'pagado' ? 'ok' : 'warn') : '—'}</td>
        <td>${badge(booking.status, statusKind)}</td>
        <td class="source-cell" title="${escapeHtml(sourceLabel(booking))}">${badge(sourceLabel(booking), booking.source_sheet ? 'warn' : 'muted')}</td>
        <td class="sync-cell">${badge(syncStatusLabel, syncStatusKind)}</td>
        <td class="booking-actions-cell">
          <div class="booking-actions">
            <button class="secondary btn-sm" type="button" data-edit-booking="${booking.id}">Editar</button>
            ${bitacoraAction}
            ${completeServiceAction}
            <button class="sync-gas-btn btn-sm btn-sync-gas-row" type="button" data-sync-booking-id="${booking.id}" title="${escapeHtml(booking.gas_last_sync_message || 'Sincronizar GAS')}">
              <span class="sync-icon ${syncStatusClass}">↻</span>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

export async function refreshStaffAvailability() {
  const box = $('#booking-availability');
  if (!box) return;
  const fields = $('#booking-form').elements;
  const staffId = idOrNull(fields.staff_id.value);
  const iso = fromDateTimeLocal(fields.scheduled_for.value);
  if (!staffId || !iso) {
    box.classList.add('hidden');
    box.innerHTML = '';
    return;
  }

  // La API trabaja en UTC; la fecha/hora consultada sale del mismo ISO que
  // se enviara al guardar, asi el aviso coincide con lo que validara el
  // backend al crear.
  const params = new URLSearchParams({ date: iso.slice(0, 10), time: iso.slice(11, 16) });
  const service = state.services.find((item) => item.id === idOrNull(fields.service_id.value));
  if (service && service.duration_minutes) params.set('duration_minutes', service.duration_minutes);

  let data;
  try {
    data = await api(`/api/v1/team/${staffId}/availability?${params}`);
  } catch (error) {
    box.classList.add('hidden');
    box.innerHTML = '';
    return;
  }

  // Al editar, la propia reserva aparece como bloque ocupado: se filtra por
  // su instante de inicio y en ese caso el aviso de choque no es confiable
  // (available considera tambien el bloque propio), asi que solo se listan
  // los bloques.
  const editingId = idOrNull(fields.id.value);
  const own = editingId ? state.bookings.find((item) => item.id === editingId) : null;
  const ownStart = own && own.scheduled_for ? Date.parse(own.scheduled_for) : null;
  const busy = data.busy.filter((slot) => ownStart === null || Date.parse(slot.start_at) !== ownStart);

  box.classList.remove('hidden');
  if (!busy.length) {
    box.className = 'availability-hint full ok';
    box.textContent = 'Sin otras reservas ese día para esta profesional.';
    return;
  }

  const conflicts = !data.available && busy.length === data.busy.length;
  box.className = `availability-hint full${conflicts ? ' warn' : ''}`;
  box.innerHTML = `
    <strong>${conflicts ? '⚠ El horario elegido choca con una reserva existente.' : 'Bloques ocupados ese día:'}</strong>
    <span class="availability-chips">${busy.map((slot) =>
      `<span class="badge ${conflicts ? 'warn' : 'muted'}" title="${escapeHtml(slot.label)}">${formatTime(slot.start_at)}–${formatTime(slot.end_at)}</span>`
    ).join('')}</span>
  `;
}

export function fillStaffSelect() {
  const activeStaff = state.staff.filter((person) => person.active);
  $('#booking-staff-select').innerHTML = '<option value="">Sin staff</option>' + activeStaff
    .map((person) => `<option value="${person.id}">${escapeHtml(person.display_name)}</option>`)
    .join('');
  $('#booking-filter-staff').innerHTML = '<option value="">Todos</option>' + activeStaff
    .map((person) => `<option value="${person.id}">${escapeHtml(person.display_name)}</option>`)
    .join('');
}

export function resetBookingForm() {
  const form = $('#booking-form');
  form.reset();
  clearFormErrors(form);
  form.querySelector('[name=id]').value = '';
  $('#booking-form-title').textContent = 'Nueva reserva';
  $('#delete-booking').disabled = true;
  $('#sync-booking').disabled = true;
  $('#complete-booking-service').disabled = true;
  $('#transfer-receipt-status').textContent = receiptStatusText();
  const availabilityBox = $('#booking-availability');
  if (availabilityBox) {
    availabilityBox.classList.add('hidden');
    availabilityBox.innerHTML = '';
  }
}

export function editBooking(id) {
  const booking = state.bookings.find((item) => item.id === id);
  if (!booking) return;
  const form = $('#booking-form');
  clearFormErrors(form);
  const fields = form.elements;
  fields.id.value = booking.id;
  fields.service_id.value = booking.service_id || '';
  fields.staff_id.value = booking.staff_id || '';
  fields.customer_name.value = booking.customer_name || '';
  fields.customer_phone.value = booking.customer_phone || '';
  fields.scheduled_for.value = toDateTimeLocal(booking.scheduled_for);
  fields.status.value = booking.status || 'draft';
  fields.address.value = booking.address || '';
  fields.comuna.value = booking.comuna || '';
  fields.service_value.value = booking.service_value || '';
  fields.transfer_value.value = booking.transfer_value || '';
  fields.deposit_amount.value = booking.deposit_amount || '';
  fields.total_service.value = booking.total_service || '';
  fields.balance_due.value = booking.balance_due || '';
  fields.payment_status.value = booking.payment_status || '';
  fields.transfer_receipt.value = '';
  $('#transfer-receipt-status').textContent = receiptStatusText(booking);
  fields.service_status.value = booking.service_status || '';
  fields.contract_id.value = booking.contract_id || '';
  fields.milestone.value = booking.milestone || '';
  fields.cash_group.value = booking.cash_group || '';
  $('#booking-form-title').textContent = `Reserva #${booking.id}`;
  $('#delete-booking').disabled = false;
  $('#sync-booking').disabled = false;
  $('#complete-booking-service').disabled = booking.status === 'completed' || booking.status === 'cancelled';
  refreshStaffAvailability();
}

export async function bookingPayload() {
  const form = $('#booking-form');
  const fields = form.elements;
  const transferReceipt = await compressedTransferReceipt(fields.transfer_receipt.files?.[0] || null);
  return {
    service_id: idOrNull(fields.service_id.value),
    staff_id: idOrNull(fields.staff_id.value),
    customer_name: fields.customer_name.value.trim() || null,
    customer_phone: fields.customer_phone.value.trim() || null,
    scheduled_for: fromDateTimeLocal(fields.scheduled_for.value),
    status: fields.status.value,
    address: fields.address.value.trim() || null,
    comuna: fields.comuna.value.trim() || null,
    service_value: numberOrNull(fields.service_value.value),
    transfer_value: numberOrNull(fields.transfer_value.value),
    deposit_amount: numberOrNull(fields.deposit_amount.value),
    total_service: numberOrNull(fields.total_service.value),
    balance_due: numberOrNull(fields.balance_due.value),
    payment_status: fields.payment_status.value.trim() || null,
    transfer_receipt: transferReceipt,
    service_status: fields.service_status.value.trim() || null,
    contract_id: fields.contract_id.value.trim() || null,
    milestone: fields.milestone.value.trim() || null,
    cash_group: fields.cash_group.value.trim() || null,
  };
}

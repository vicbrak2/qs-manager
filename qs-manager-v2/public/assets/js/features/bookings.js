
import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';
import { escapeHtml, money, badge, sourceLabel, dash, formatDate, text, toDateTimeLocal, fromDateTimeLocal, numberOrNull, idOrNull } from '../ui/formatting.js';
import { notify } from '../ui/notifications.js';
import { clearFormErrors, showFormErrors } from '../ui/validation.js';
import { refreshMetrics } from './dashboard.js';

export async function loadBookings() {
  const data = await api('/api/v1/bookings');
  state.bookings = data.bookings;
  renderBookings();
  refreshMetrics();
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

export function renderBookings() {
  const filtered = state.bookings.filter(bookingMatches);
  $('#bookings-empty').style.display = filtered.length ? 'none' : 'block';
  
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
    
    return `
      <tr>
        <td>${booking.id}</td>
        <td>${formatDate(booking.scheduled_for)}</td>
        <td class="wrap">${escapeHtml(booking.customer_name || '')}</td>
        <td>${escapeHtml(dash(booking.customer_phone))}</td>
        <td class="wrap">${escapeHtml(booking.service_name || '')}</td>
        <td>${escapeHtml(dash(booking.comuna))}</td>
        <td class="wrap">${escapeHtml(dash(booking.address))}</td>
        <td>${money(booking.total_service)}</td>
        <td>${money(booking.balance_due)}</td>
        <td>${badge(booking.payment_status || '', 'muted')}</td>
        <td>${badge(booking.status, statusKind)}</td>
        <td>${badge(sourceLabel(booking), booking.source_sheet ? 'warn' : 'muted')}<br>${badge(syncStatusLabel, syncStatusKind)}</td>
        <td>
          <div style="display: flex; gap: 6px; align-items: center;">
            <button class="secondary btn-sm" type="button" data-edit-booking="${booking.id}">Editar</button>
            <button class="sync-gas-btn btn-sm btn-sync-gas-row" type="button" data-sync-booking-id="${booking.id}" title="${escapeHtml(booking.gas_last_sync_message || 'Sincronizar GAS')}">
              <span class="sync-icon ${syncStatusClass}">↻</span>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
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
  fields.service_status.value = booking.service_status || '';
  fields.contract_id.value = booking.contract_id || '';
  fields.milestone.value = booking.milestone || '';
  fields.cash_group.value = booking.cash_group || '';
  $('#booking-form-title').textContent = `Reserva #${booking.id}`;
  $('#delete-booking').disabled = false;
  $('#sync-booking').disabled = false;
}

export function bookingPayload() {
  const form = $('#booking-form');
  const fields = form.elements;
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
    service_status: fields.service_status.value.trim() || null,
    contract_id: fields.contract_id.value.trim() || null,
    milestone: fields.milestone.value.trim() || null,
    cash_group: fields.cash_group.value.trim() || null,
  };
}

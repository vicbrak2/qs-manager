
import { state } from './state.js';
import { api } from './api.js';
import { $ } from './dom.js';
import { notify } from './ui/notifications.js';
import { clearFormErrors, showFormErrors } from './ui/validation.js';

import { setTab, loadHealth, loadSyncStatus } from './features/dashboard.js';
import { loadServices, resetServiceForm, editService, servicePayload, renderServices, toggleServiceSort } from './features/services.js';
import { loadStaff, loadBookings, resetBookingForm, editBooking, bookingPayload, renderBookings, bookingMatches, toggleBookingSort } from './features/bookings.js';
import { syncAll, renderSyncModal, syncBookingGas } from './features/sync.js';
import { loadFinanceDashboard } from './features/finance.js';

async function boot() {
  await Promise.all([loadHealth(), loadSyncStatus(), loadServices(), loadStaff(), loadBookings(), loadFinanceDashboard()]);
}

$('#tab-finance').addEventListener('click', () => {
  setTab('finance');
  if (!$('#finance-val-contracted').dataset.loaded) {
    loadFinanceDashboard();
    $('#finance-val-contracted').dataset.loaded = 'true';
  }
});
$('#tab-services').addEventListener('click', () => setTab('services'));
$('#tab-bookings').addEventListener('click', () => setTab('bookings'));
$('#sync-all').addEventListener('click', syncAll);
$('#refresh-finance').addEventListener('click', loadFinanceDashboard);
$('#refresh-services').addEventListener('click', loadServices);
$('#refresh-bookings').addEventListener('click', loadBookings);
$('#new-service').addEventListener('click', resetServiceForm);
$('#reset-service').addEventListener('click', resetServiceForm);
$('#new-booking').addEventListener('click', resetBookingForm);
$('#reset-booking').addEventListener('click', resetBookingForm);

['#service-filter-text', '#service-filter-active', '#service-filter-source'].forEach((selector) => {
  $(selector).addEventListener('input', renderServices);
  $(selector).addEventListener('change', renderServices);
});

['#booking-filter-text', '#booking-filter-service', '#booking-filter-staff', '#booking-filter-status', '#booking-filter-payment'].forEach((selector) => {
  const el = $(selector);
  if (el) {
    el.addEventListener('input', () => {
      state.bookingsPagination.currentPage = 1;
      renderBookings();
    });
    el.addEventListener('change', () => {
      state.bookingsPagination.currentPage = 1;
      renderBookings();
    });
  }
});

$('#booking-prev-page').addEventListener('click', () => {
  if (state.bookingsPagination.currentPage > 1) {
    state.bookingsPagination.currentPage--;
    renderBookings();
  }
});

$('#booking-next-page').addEventListener('click', () => {
  const perPage = Number($('#booking-per-page').value);
  const filtered = state.bookings.filter(bookingMatches);
  const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
  if (state.bookingsPagination.currentPage < totalPages) {
    state.bookingsPagination.currentPage++;
    renderBookings();
  }
});

$('#booking-per-page').addEventListener('change', () => {
  state.bookingsPagination.currentPage = 1;
  renderBookings();
});

document.addEventListener('click', async (event) => {
  const serviceSortButton = event.target.closest('[data-service-sort]');
  if (serviceSortButton) toggleServiceSort(serviceSortButton.dataset.serviceSort);

  const bookingSortButton = event.target.closest('[data-booking-sort]');
  if (bookingSortButton) toggleBookingSort(bookingSortButton.dataset.bookingSort);

  const serviceButton = event.target.closest('[data-edit-service]');
  if (serviceButton) editService(Number(serviceButton.dataset.editService));

  const bookingButton = event.target.closest('[data-edit-booking]');
  if (bookingButton) editBooking(Number(bookingButton.dataset.editBooking));

  const syncRowButton = event.target.closest('[data-sync-booking-id]');
  if (syncRowButton) {
    const id = Number(syncRowButton.dataset.syncBookingId);
    await syncBookingGas(id, syncRowButton);
  }
});

$('#service-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = $('#service-form');
  const submitBtn = $('#save-service');
  submitBtn.disabled = true;
  clearFormErrors(form);
  const id = form.querySelector('[name=id]').value;
  try {
    await api(id ? `/api/v1/services/${id}` : '/api/v1/services', {
      method: id ? 'PUT' : 'POST',
      body: JSON.stringify(id ? servicePayload() : {
        name: form.elements.name.value.trim(),
        category: form.elements.category.value.trim() || null,
        duration_minutes: form.elements.duration_minutes.value ? Number(form.elements.duration_minutes.value) : null,
        quantity: form.elements.quantity.value ? Number(form.elements.quantity.value) : 1,
      }),
    });
    notify(id ? 'Servicio actualizado.' : 'Servicio creado.');
    resetServiceForm();
    await loadServices();
  } catch (error) {
    notify(error.message, true);
    if (error.errors) {
      showFormErrors(form, error.errors);
    }
  } finally {
    submitBtn.disabled = false;
  }
});

$('#delete-service').addEventListener('click', async () => {
  const id = $('#service-form [name=id]').value;
  if (!id || !confirm('Borrar este servicio local?')) return;
  const deleteBtn = $('#delete-service');
  deleteBtn.disabled = true;
  try {
    await api(`/api/v1/services/${id}`, { method: 'DELETE' });
    notify('Servicio borrado.');
    resetServiceForm();
    await loadServices();
  } catch (error) {
    notify(error.message, true);
  } finally {
    const currentId = $('#service-form [name=id]').value;
    if (currentId) {
      deleteBtn.disabled = false;
    }
  }
});

$('#booking-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = $('#booking-form');
  const submitBtn = $('#save-booking');
  submitBtn.disabled = true;
  clearFormErrors(form);
  const id = form.querySelector('[name=id]').value;
  try {
    await api(id ? `/api/v1/bookings/${id}` : '/api/v1/bookings', {
      method: id ? 'PUT' : 'POST',
      body: JSON.stringify(bookingPayload()),
    });
    notify(id ? 'Reserva actualizada.' : 'Reserva creada.');
    resetBookingForm();
    await loadBookings();
  } catch (error) {
    notify(error.message, true);
    if (error.errors) {
      showFormErrors(form, error.errors);
    }
  } finally {
    submitBtn.disabled = false;
  }
});

$('#delete-booking').addEventListener('click', async () => {
  const id = $('#booking-form [name=id]').value;
  if (!id || !confirm('Borrar esta reserva local?')) return;
  const deleteBtn = $('#delete-booking');
  deleteBtn.disabled = true;
  try {
    await api(`/api/v1/bookings/${id}`, { method: 'DELETE' });
    notify('Reserva borrada.');
    resetBookingForm();
    await loadBookings();
  } catch (error) {
    notify(error.message, true);
  } finally {
    const currentId = $('#booking-form [name=id]').value;
    if (currentId) {
      deleteBtn.disabled = false;
    }
  }
});

$('#sync-booking').addEventListener('click', async () => {
  const id = $('#booking-form [name=id]').value;
  if (!id) return;
  const syncRowButton = document.querySelector(`[data-sync-booking-id="${id}"]`);
  await syncBookingGas(Number(id), syncRowButton);
});

$('#metric-sync').parentElement.style.cursor = 'pointer';
$('#metric-sync').parentElement.addEventListener('click', async () => {
  try {
    const run = await api('/api/v1/sync/sheets/runs/last');
    if (!run.error) renderSyncModal(run);
  } catch (e) {
    notify('No hay sincronizaciones previas o la base de datos está vacía.', 'error', 3000);
  }
});

const closeSyncModalBtn = $('#close-sync-modal');
if (closeSyncModalBtn) {
  closeSyncModalBtn.addEventListener('click', () => $('#sync-modal').close());
}

boot().catch((error) => notify(error.message, true));

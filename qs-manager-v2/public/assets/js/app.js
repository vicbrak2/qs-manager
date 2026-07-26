
import { state } from './state.js';
import { api } from './api.js';
import { $ } from './dom.js';
import { notify } from './ui/notifications.js';
import { clearFormErrors, showFormErrors } from './ui/validation.js';

import { setTab, loadHealth, loadSyncStatus } from './features/dashboard.js';
import { loadServices, resetServiceForm, editService, servicePayload, renderServices, toggleServiceSort } from './features/services.js';
import { loadStaff, loadBookings, resetBookingForm, editBooking, bookingPayload, renderBookings, visibleBookings, toggleBookingSort, setBookingsView, refreshStaffAvailability } from './features/bookings.js';
import { loadBitacoras, resetBitacoraForm, editBitacora, startBitacoraFromBooking, bitacoraPayload, fillBitacoraStaffSelects, addBitacoraNote, addTramoRow, updateBitacoraPlan, copyTeamBitacora } from './features/bitacora.js';
import { syncAll, renderSyncModal, syncBookingGas } from './features/sync.js';
import { initFinanceDetails, loadFinanceDashboard } from './features/finance.js';

async function boot() {
  initFinanceDetails();
  await Promise.all([loadHealth(), loadSyncStatus(), loadServices(), loadStaff(), loadBookings(), loadFinanceDashboard()]);
  fillBitacoraStaffSelects();
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
$('#tab-bitacora').addEventListener('click', () => {
  setTab('bitacora');
  if (!$('#bitacoras-body').dataset.loaded) {
    loadBitacoras().catch((error) => notify(error.message, true));
    $('#bitacoras-body').dataset.loaded = 'true';
  }
});
$('#refresh-bitacoras').addEventListener('click', () => loadBitacoras().catch((error) => notify(error.message, true)));
$('#new-bitacora').addEventListener('click', resetBitacoraForm);
$('#reset-bitacora').addEventListener('click', resetBitacoraForm);
$('#add-bitacora-note').addEventListener('click', addBitacoraNote);
$('#add-tramo').addEventListener('click', () => {
  addTramoRow();
  updateBitacoraPlan();
});
$('#copy-bitacora-team').addEventListener('click', copyTeamBitacora);

// El plan (salida/llegada/faltantes) se recalcula con cualquier dato que
// participe del calculo, incluidos los tramos que se crean dinamicamente.
$('#bitacora-form').addEventListener('input', (event) => {
  if (event.target.closest('#tramos-list') || event.target.matches('[name=hora_inicio_servicio], [name=clienta_nombre], [name=direccion_servicio], [name=punto_salida]')) {
    updateBitacoraPlan();
  }
});

['staff_id', 'scheduled_for', 'service_id'].forEach((name) => {
  $('#booking-form').elements[name].addEventListener('change', refreshStaffAvailability);
});
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
  const filtered = visibleBookings();
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
  const bookingViewButton = event.target.closest('[data-booking-view]');
  if (bookingViewButton) setBookingsView(bookingViewButton.dataset.bookingView);

  const serviceSortButton = event.target.closest('[data-service-sort]');
  if (serviceSortButton) toggleServiceSort(serviceSortButton.dataset.serviceSort);

  const bookingSortButton = event.target.closest('[data-booking-sort]');
  if (bookingSortButton) toggleBookingSort(bookingSortButton.dataset.bookingSort);

  const serviceButton = event.target.closest('[data-edit-service]');
  if (serviceButton) editService(Number(serviceButton.dataset.editService));

  const bookingButton = event.target.closest('[data-edit-booking]');
  if (bookingButton) editBooking(Number(bookingButton.dataset.editBooking));

  const createBitacoraButton = event.target.closest('[data-create-bitacora]');
  if (createBitacoraButton) {
    const booking = state.bookings.find((item) => item.id === Number(createBitacoraButton.dataset.createBitacora));
    if (!$('#bitacoras-body').dataset.loaded) {
      loadBitacoras().catch((error) => notify(error.message, true));
      $('#bitacoras-body').dataset.loaded = 'true';
    }
    startBitacoraFromBooking(booking);
  }

  const openBitacoraButton = event.target.closest('[data-open-bitacora]');
  if (openBitacoraButton) {
    setTab('bitacora');
    const targetId = Number(openBitacoraButton.dataset.openBitacora);
    // Recargar si la bitacora no esta en el estado (lista nunca cargada o
    // desactualizada respecto a otra sesion).
    if (!state.bitacoras.some((item) => item.id === targetId)) {
      await loadBitacoras();
      $('#bitacoras-body').dataset.loaded = 'true';
    }
    editBitacora(targetId);
  }

  const bitacoraButton = event.target.closest('[data-edit-bitacora]');
  if (bitacoraButton) editBitacora(Number(bitacoraButton.dataset.editBitacora));

  const removeTramoButton = event.target.closest('[data-remove-tramo]');
  if (removeTramoButton) {
    removeTramoButton.closest('[data-tramo]').remove();
    updateBitacoraPlan();
  }

  const bitacoraBookingLink = event.target.closest('[data-bitacora-booking-link]');
  if (bitacoraBookingLink) {
    setTab('bookings');
    editBooking(Number(bitacoraBookingLink.dataset.bitacoraBookingLink));
  }

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
      body: JSON.stringify(servicePayload()),
    });
    notify(id ? 'Servicio actualizado.' : 'Servicio publicado en Servicios Master y Seguimiento Contable.');
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

$('#bitacora-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = $('#bitacora-form');
  const submitBtn = $('#save-bitacora');
  submitBtn.disabled = true;
  clearFormErrors(form);
  const id = form.querySelector('[name=id]').value;
  try {
    await api(id ? `/api/v1/bitacoras/${id}` : '/api/v1/bitacoras', {
      method: id ? 'PUT' : 'POST',
      body: JSON.stringify(bitacoraPayload()),
    });
    notify(id ? 'Bitácora actualizada.' : 'Bitácora creada.');
    resetBitacoraForm();
    await loadBitacoras();
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

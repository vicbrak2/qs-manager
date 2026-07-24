
import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';
import { escapeHtml, badge, toDateTimeLocal } from '../ui/formatting.js';
import { notify } from '../ui/notifications.js';
import { renderBookings } from './bookings.js';
import { loadServices } from './services.js';
import { loadStaff, loadBookings } from './bookings.js';
import { loadHealth, loadSyncStatus } from './dashboard.js';

export async function syncAll() {
  const button = $('#sync-all');
  const label = $('#sync-all-label');
  button.disabled = true;
  button.setAttribute('aria-busy', 'true');
  label.textContent = 'Sincronizando...';
  const toast = notify('Sincronización encolada. Consultando estado...', 'loading', 0);

  try {
    const result = await api('/api/v1/sync/sheets/import', { method: 'POST' });
    if (result.error) {
      throw new Error(result.error);
    }

    const runId = result.run_id;
    
    let finalRun = null;
    let attempts = 0;
    const maxAttempts = 150;

    while (attempts < maxAttempts) {
        attempts++;
        await new Promise(resolve => setTimeout(resolve, 2000));
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            const poll = await api(`/api/v1/sync/sheets/runs/${runId}`, { signal: controller.signal });
            clearTimeout(timeoutId);
            
            if (poll.status === 'completed' || poll.status === 'partial' || poll.status === 'failed') {
                finalRun = poll;
                break;
            }
        } catch (err) {
            if (err.name !== 'AbortError') console.error('Polling error:', err);
        }
    }

    await Promise.all([loadHealth(), loadSyncStatus(), loadServices(), loadStaff(), loadBookings()]);

    if (!finalRun) {
        toast.update('La sincronización continúa en segundo plano.', 'success', 8000);
        return;
    }

    if (finalRun.status === 'completed') {
        toast.update(`Sincronización completa: ${finalRun.total_rows_imported} filas importadas.`, 'success', 5000);
    } else if (finalRun.status === 'partial') {
        toast.update(`Sincronización parcial: ${finalRun.failed_sources} fuentes fallaron.`, 'error', 8000);
    } else {
        toast.update(`Error en la sincronización.`, 'error', 8000);
    }
    
    renderSyncModal(finalRun);

  } catch (error) {
    toast.update(error.message, 'error', 7000);
  } finally {
    button.disabled = false;
    button.removeAttribute('aria-busy');
    label.textContent = 'Sincronizar todo';
  }
}

export function renderSyncModal(run) {
  const modal = $('#sync-modal');
  const body = $('#sync-modal-body');
  
  let html = `<div style="margin-bottom: 1.5rem;">
    <p><strong>Estado Global:</strong> ${badge(run.status, run.status === 'completed' ? 'ok' : (run.status === 'failed' ? 'error' : 'warn'))}</p>
    <p><strong>Iniciado:</strong> ${toDateTimeLocal(run.started_at).replace('T', ' ')}</p>
    <p><strong>Terminado:</strong> ${run.finished_at ? toDateTimeLocal(run.finished_at).replace('T', ' ') : '-'}</p>
    <p><strong>Resumen:</strong> ${run.completed_sources}/${run.total_sources} fuentes. ${run.total_rows_imported}/${run.total_rows_seen} filas importadas.</p>
  </div>`;
  
  const groups = {
    'Servicios': ['services_master', 'service_catalog', 'agenda_values'],
    'Reservas': ['bitacora', 'agenda_month'],
    'Talleres': ['workshops'],
    'Finanzas': ['cash_tracking', 'operational_expenses', 'fixed_expenses']
  };
  
  for (const [groupName, purposes] of Object.entries(groups)) {
    const groupSources = (run.sources || []).filter(s => purposes.includes(s.purpose));
    if (groupSources.length === 0) continue;
    
    html += `<h3 style="margin-top: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">${groupName}</h3>`;
    html += `<div class="table-wrap" style="margin-top: 0.5rem;"><table>
      <thead><tr><th>Hoja</th><th>Estado</th><th>Filas</th><th>Tiempo</th><th>Mensaje</th></tr></thead>
      <tbody>`;
    
    for (const s of groupSources) {
      html += `<tr>
        <td>${escapeHtml(s.sheet_name)}</td>
        <td>${badge(s.status, s.status === 'completed' ? 'ok' : 'error')}</td>
        <td>${s.rows_imported}/${s.rows_seen}</td>
        <td>${s.duration_ms ? s.duration_ms + 'ms' : '-'}</td>
        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(s.error_message || '')}">${escapeHtml(s.error_message || '')}</td>
      </tr>`;
    }
    html += `</tbody></table></div>`;
  }
  
  body.innerHTML = html;
  modal.showModal();
}

export async function syncBookingGas(id, rowButton) {
  const booking = state.bookings.find(b => b.id === id);
  if (!booking) return;

  let icon = null;
  if (rowButton) {
    icon = rowButton.querySelector('.sync-icon');
    rowButton.disabled = true;
    if (icon) {
      icon.className = 'sync-icon spinning';
    }
  }

  const formSyncButton = $('#sync-booking');
  const formId = $('#booking-form [name=id]').value;
  const isFormEditing = (formId && Number(formId) === id);
  if (isFormEditing) {
    formSyncButton.disabled = true;
  }

  const toast = notify(`Sincronizando reserva #${id} con GAS...`, 'loading', 0);

  try {
    const result = await api(`/api/v1/bookings/${id}/sync-gas`, { method: 'POST' });
    
    booking.gas_last_sync_status = result.sync.status;
    booking.gas_last_sync_message = result.sync.message || '';
    
    if (result.sync.status === 'synced' || result.sync.status === 'success') {
      toast.update(`Reserva #${id} sincronizada con GAS con éxito.`, 'success', 5000);
    } else {
      toast.update(`Sincronización GAS: ${result.sync.status}. ${result.sync.message || ''}`, 'success', 5000);
    }
  } catch (error) {
    toast.update(`Error al sincronizar reserva #${id} con GAS: ${error.message}`, 'error', 5000);
    booking.gas_last_sync_status = 'failed';
    booking.gas_last_sync_message = error.message;
  } finally {
    if (rowButton) {
      rowButton.disabled = false;
      if (icon) {
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
        icon.className = `sync-icon ${syncStatusClass}`;
      }
    }
    if (isFormEditing) {
      formSyncButton.disabled = false;
    }
    renderBookings();
  }
}

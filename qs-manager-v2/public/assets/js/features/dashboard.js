
import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';

export function setTab(tab) {
  state.activeTab = tab;
  $('#tab-finance').classList.toggle('active', tab === 'finance');
  $('#tab-services').classList.toggle('active', tab === 'services');
  $('#tab-bookings').classList.toggle('active', tab === 'bookings');
  $('#finance-view').classList.toggle('hidden', tab !== 'finance');
  $('#services-view').classList.toggle('hidden', tab !== 'services');
  $('#bookings-view').classList.toggle('hidden', tab !== 'bookings');
}

export function refreshMetrics() {
  $('#metric-services').textContent = state.services.length;
  $('#metric-bookings').textContent = state.bookings.length;
  $('#metric-confirmed').textContent = state.bookings.filter((booking) => booking.status === 'confirmed').length;
}

export async function loadHealth() {
  const health = await api('/health');
  $('#health').textContent = `API ${health.status} · DB ${health.database}`;
  $('#health-dot').classList.toggle('ok', health.status === 'ok');
}

export async function loadSyncStatus() {
  const status = await api('/api/v1/sync/sheets/status');
  const completed = status.sources.filter((source) => source.last_run_status === 'completed').length;
  $('#metric-sync').textContent = status.enabled ? `${completed}/${status.sources.length}` : 'off';
}

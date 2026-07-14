
import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';
import { escapeHtml, money, percent, badge, sourceLabel, numberOrNull } from '../ui/formatting.js';
import { notify } from '../ui/notifications.js';
import { clearFormErrors, showFormErrors } from '../ui/validation.js';
import { refreshMetrics } from './dashboard.js';

export async function loadServices() {
  const data = await api('/api/v1/services');
  state.services = data.services;
  renderServices();
  fillServiceSelect();
  refreshMetrics();
}

export function serviceMatches(service) {
  const query = $('#service-filter-text').value.trim().toLowerCase();
  const active = $('#service-filter-active').value;
  const source = $('#service-filter-source').value;
  const haystack = [service.name, service.category, service.margin_status, service.source_sheet].join(' ').toLowerCase();
  if (query && !haystack.includes(query)) return false;
  if (active && String(service.active) !== active) return false;
  if (source === 'local' && service.source_sheet) return false;
  if (source === 'sheet' && !service.source_sheet) return false;
  return true;
}

export function renderServices() {
  const rows = state.services.filter(serviceMatches);
  $('#services-empty').style.display = rows.length ? 'none' : 'block';
  $('#services-body').innerHTML = rows.map((service) => `
    <tr>
      <td>${service.id}</td>
      <td class="wrap">${escapeHtml(service.name)}</td>
      <td>${escapeHtml(service.category || '')}</td>
      <td>${money(service.sale_price)}</td>
      <td>${money(service.total_cost)}</td>
      <td>${money(service.utility)}</td>
      <td>${percent(service.margin_percent)}</td>
      <td>${badge(sourceLabel(service), service.source_sheet ? 'warn' : 'muted')}</td>
      <td>${badge(service.active ? 'activo' : 'inactivo', service.active ? 'ok' : 'muted')}</td>
      <td><button class="secondary btn-sm" type="button" data-edit-service="${service.id}">Editar</button></td>
    </tr>
  `).join('');
}

export function fillServiceSelect() {
  const activeServices = state.services.filter((service) => service.active);
  $('#booking-service-select').innerHTML = '<option value="">Sin servicio</option>' + activeServices
    .map((service) => `<option value="${service.id}">${escapeHtml(service.name)}</option>`)
    .join('');
  $('#booking-filter-service').innerHTML = '<option value="">Todos</option>' + activeServices
    .map((service) => `<option value="${service.id}">${escapeHtml(service.name)}</option>`)
    .join('');
}

export function resetServiceForm() {
  const form = $('#service-form');
  form.reset();
  clearFormErrors(form);
  form.querySelector('[name=id]').value = '';
  $('#service-form-title').textContent = 'Nuevo servicio';
  $('#delete-service').disabled = true;
}

export function editService(id) {
  const service = state.services.find((item) => item.id === id);
  if (!service) return;
  const form = $('#service-form');
  clearFormErrors(form);
  const fields = form.elements;
  fields.id.value = service.id;
  fields.name.value = service.name || '';
  fields.category.value = service.category || '';
  fields.duration_minutes.value = service.duration_minutes || '';
  fields.sale_price.value = service.sale_price || '';
  fields.total_cost.value = service.total_cost || '';
  fields.utility.value = service.utility || '';
  fields.margin_percent.value = service.margin_percent || '';
  fields.margin_status.value = service.margin_status || '';
  fields.active.value = String(Boolean(service.active));
  $('#service-form-title').textContent = `Servicio #${service.id}`;
  $('#delete-service').disabled = false;
}

export function servicePayload() {
  const form = $('#service-form');
  const fields = form.elements;
  return {
    name: fields.name.value.trim(),
    category: fields.category.value.trim() || null,
    duration_minutes: numberOrNull(fields.duration_minutes.value),
    active: fields.active.value === 'true',
    sale_price: numberOrNull(fields.sale_price.value),
    total_cost: numberOrNull(fields.total_cost.value),
    utility: numberOrNull(fields.utility.value),
    margin_percent: numberOrNull(fields.margin_percent.value),
    margin_status: fields.margin_status.value.trim() || null,
  };
}

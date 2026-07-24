
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

const numericServiceSortKeys = new Set(['id', 'quantity', 'sale_price', 'total_cost', 'utility', 'margin_percent']);

function serviceSortValue(service, key) {
  const value = service[key];
  if (value === null || value === undefined || value === '') return null;
  if (key === 'active') return value ? 1 : 0;
  if (numericServiceSortKeys.has(key)) {
    const number = Number(value);
    return Number.isNaN(number) ? null : number;
  }
  return String(value).trim().toLocaleLowerCase('es-CL');
}

function compareServices(left, right) {
  const { key, direction } = state.servicesSort;
  const leftValue = serviceSortValue(left, key);
  const rightValue = serviceSortValue(right, key);
  if (leftValue === null && rightValue === null) return Number(left.id) - Number(right.id);
  if (leftValue === null) return 1;
  if (rightValue === null) return -1;

  let comparison = typeof leftValue === 'string'
    ? leftValue.localeCompare(rightValue, 'es-CL', { numeric: true, sensitivity: 'base' })
    : leftValue - rightValue;
  if (comparison === 0) comparison = Number(left.id) - Number(right.id);
  return direction === 'asc' ? comparison : -comparison;
}

function updateServiceSortHeaders() {
  document.querySelectorAll('[data-service-sort]').forEach((button) => {
    const active = button.dataset.serviceSort === state.servicesSort.key;
    const header = button.closest('th');
    const indicator = button.querySelector('.sort-indicator');
    button.classList.toggle('active', active);
    header.removeAttribute('aria-sort');
    indicator.textContent = '';
    if (active) {
      const ascending = state.servicesSort.direction === 'asc';
      header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
      indicator.textContent = ascending ? '▲' : '▼';
    }
  });
}

export function toggleServiceSort(key) {
  if (state.servicesSort.key === key) {
    state.servicesSort.direction = state.servicesSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    state.servicesSort.key = key;
    state.servicesSort.direction = 'asc';
  }
  renderServices();
}

function profitabilityClass(margin) {
  if (margin === null || margin === undefined || margin === '') return '';
  const value = Number(margin);
  if (!Number.isFinite(value)) return '';
  if (value < 0.2) return 'service-profit-danger';
  if (value < 0.3) return 'service-profit-warning';
  if (value < 0.4) return 'service-profit-success';
  return 'service-profit-high';
}

export function renderServices() {
  const rows = state.services.filter(serviceMatches).sort(compareServices);
  $('#services-empty').style.display = rows.length ? 'none' : 'block';
  updateServiceSortHeaders();
  $('#services-body').innerHTML = rows.map((service) => {
    const source = service.source_sheet === 'Servicios_Master'
      ? 'Servicios Master'
      : service.source_sheet || 'Local';

    return `
    <tr class="${profitabilityClass(service.margin_percent)}">
      <td>${service.id}</td>
      <td class="wrap">${escapeHtml(service.name)}</td>
      <td>${escapeHtml(service.category || '')}</td>
      <td>${service.quantity ?? 1}</td>
      <td>${money(service.sale_price)}</td>
      <td>${money(service.total_cost)}</td>
      <td>${money(service.utility)}</td>
      <td>${percent(service.margin_percent)}</td>
      <td class="source-cell" title="${escapeHtml(sourceLabel(service))}">${badge(source, service.source_sheet ? 'warn' : 'muted')}</td>
      <td>${badge(service.active ? 'activo' : 'inactivo', service.active ? 'ok' : 'muted')}</td>
      <td><button class="secondary btn-sm" type="button" data-edit-service="${service.id}">Editar</button></td>
    </tr>
  `;
  }).join('');
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
  fields.quantity.value = service.quantity || 1;
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
    quantity: numberOrNull(fields.quantity.value) || 1,
    active: fields.active.value === 'true',
    sale_price: numberOrNull(fields.sale_price.value),
    total_cost: numberOrNull(fields.total_cost.value),
    utility: numberOrNull(fields.utility.value),
    margin_percent: numberOrNull(fields.margin_percent.value),
    margin_status: fields.margin_status.value.trim() || null,
  };
}

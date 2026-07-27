
import { state } from '../state.js';
import { api } from '../api.js';
import { $ } from '../dom.js';
import { escapeHtml, badge, dash } from '../ui/formatting.js';
import { notify } from '../ui/notifications.js';
import { clearFormErrors } from '../ui/validation.js';

export async function loadTeam() {
  const data = await api('/api/v1/team');
  state.staff = data.staff;
  renderTeam();
}

export function renderTeam() {
  const rows = state.staff;
  $('#team-empty').style.display = rows.length ? 'none' : 'block';
  $('#team-body').innerHTML = rows.map((person) => `
    <tr class="${person.active ? '' : 'staff-inactive'}">
      <td>${person.id}</td>
      <td class="wrap">${escapeHtml(person.display_name)}</td>
      <td>${badge(person.role, person.role === 'staff' ? 'muted' : 'warn')}</td>
      <td>${escapeHtml(dash(person.phone))}</td>
      <td>${escapeHtml(dash(person.comuna_base))}</td>
      <td class="wrap">${escapeHtml(dash((person.aliases || []).join(', ')))}</td>
      <td>${badge(person.active ? 'Sí' : 'No', person.active ? 'ok' : 'muted')}</td>
      <td><button class="secondary btn-sm" type="button" data-edit-staff="${person.id}">Editar</button></td>
    </tr>
  `).join('');
}

export function resetStaffForm() {
  const form = $('#staff-form');
  form.reset();
  clearFormErrors(form);
  form.querySelector('[name=id]').value = '';
  $('#staff-form-title').textContent = 'Nueva profesional';
  $('#delete-staff').disabled = true;
}

export function editStaff(id) {
  const person = state.staff.find((item) => item.id === id);
  if (!person) return;
  const form = $('#staff-form');
  clearFormErrors(form);
  const fields = form.elements;
  fields.id.value = person.id;
  fields.display_name.value = person.display_name;
  fields.role.value = person.role;
  fields.active.value = person.active ? 'true' : 'false';
  fields.phone.value = person.phone || '';
  fields.comuna_base.value = person.comuna_base || '';
  fields.aliases.value = (person.aliases || []).join(', ');
  $('#staff-form-title').textContent = `${person.display_name} (#${person.id})`;
  $('#delete-staff').disabled = false;
}

export function staffPayload() {
  const fields = $('#staff-form').elements;
  return {
    display_name: fields.display_name.value.trim(),
    role: fields.role.value,
    active: fields.active.value === 'true',
    phone: fields.phone.value.trim() || null,
    comuna_base: fields.comuna_base.value.trim() || null,
    aliases: fields.aliases.value.trim(),
  };
}

export async function deleteStaff() {
  const id = $('#staff-form [name=id]').value;
  if (!id || !confirm('¿Borrar esta profesional? Si tiene servicios asociados, mejor desactivala.')) return;
  try {
    await api(`/api/v1/team/${id}`, { method: 'DELETE' });
    notify('Profesional borrada.');
    resetStaffForm();
    await loadTeam();
  } catch (error) {
    notify(error.message, true);
  }
}

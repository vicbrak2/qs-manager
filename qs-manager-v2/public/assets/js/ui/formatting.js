
export const money = (value) => value === null || value === undefined || value === '' ? '' : Number(value).toLocaleString('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });
export const text = (value) => value === null || value === undefined ? '' : String(value);
export const dash = (value) => value === null || value === undefined || value === '' ? '—' : value;
export const percent = (value) => value === null || value === undefined || value === '' ? '' : `${(Number(value) * 100).toLocaleString('es-CL', { maximumFractionDigits: 1 })}%`;
export const numberOrNull = (value) => value === '' || value === null || value === undefined ? null : Number(value);
export const idOrNull = (value) => value === '' || value === null || value === undefined ? null : Number(value);

export function escapeHtml(value) {
  return text(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

export function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
}

export function toDateTimeLocal(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function fromDateTimeLocal(value) {
  if (!value) return null;
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? null : d.toISOString();
}

export function badge(value, kind = 'muted') {
  return `<span class="badge ${kind}">${escapeHtml(value || '')}</span>`;
}

export function sourceLabel(row) {
  return row.source_sheet ? `${row.source_sheet} #${row.source_row || ''}` : 'local';
}

import { api } from '../api.js';
import { $ } from '../dom.js';
import { notify } from '../ui/notifications.js';

let financeChartInstance = null;
let availableDetailsLoadedFor = '';
let fixedExpenseDetailsLoadedFor = '';

const formatCurrency = (val) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(val);
const monthLabels = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

function todayInChile() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Santiago',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date()).reduce((carry, part) => {
    carry[part.type] = part.value;
    return carry;
  }, {});

  return { year: Number(parts.year), month: Number(parts.month), day: Number(parts.day) };
}

function monthRange(value) {
  const [year, month] = value.split('-').map(Number);
  const lastDay = new Date(year, month, 0).getDate();

  return {
    from: `${year}-${String(month).padStart(2, '0')}-01`,
    to: `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`,
  };
}

function ensureFinancePeriodControls() {
  const monthSelect = $('#finance-month');
  if (!monthSelect.dataset.loaded) {
    const { year, month } = todayInChile();
    monthSelect.innerHTML = monthLabels.map((label, index) => {
      const monthValue = `${year}-${String(index + 1).padStart(2, '0')}`;
      return `<option value="${monthValue}">${label} ${year}</option>`;
    }).join('');
    monthSelect.value = `${year}-${String(month).padStart(2, '0')}`;
    monthSelect.dataset.loaded = 'true';
  }

  applyFinancePeriodMode();
}

function isRangeMode() {
  return Boolean($('#finance-use-range')?.checked);
}

function applyFinancePeriodMode() {
  const rangeMode = isRangeMode();
  const monthSelect = $('#finance-month');
  const fromEl = $('#finance-from');
  const toEl = $('#finance-to');

  monthSelect.disabled = rangeMode;
  fromEl.disabled = !rangeMode;
  toEl.disabled = !rangeMode;

  if (!rangeMode) {
    const range = monthRange(monthSelect.value);
    fromEl.value = range.from;
    toEl.value = range.to;
  } else if (!fromEl.value || !toEl.value) {
    const range = monthRange(monthSelect.value);
    fromEl.value = range.from;
    toEl.value = range.to;
  }
}

function formatDate(value) {
  if (!value) return '--';
  const [year, month, day] = value.split('-').map(Number);
  return new Intl.DateTimeFormat('es-CL').format(new Date(year, month - 1, day));
}

async function loadAvailableDetails(force = false) {
  const from = $('#finance-from').value;
  const to = $('#finance-to').value;
  const basis = $('#finance-basis').value || 'cash_estimated';
  const periodKey = `${from}|${to}|${basis}`;
  if (!force && availableDetailsLoadedFor === periodKey) return;

  const body = $('#finance-details-body');
  body.innerHTML = '<tr><td colspan="6">Cargando servicios abonados...</td></tr>';

  const data = await api(`/api/v1/finance/available-details?from=${from}&to=${to}&basis=${basis}`);
  body.innerHTML = '';

  if (!data.services.length) {
    body.innerHTML = '<tr><td colspan="6" class="finance-details-empty">No hay servicios con abonos en este período.</td></tr>';
  } else {
    data.services.forEach(row => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${formatDate(row.occurred_on)}</td>
        <td>${escapeHtml(row.customer)}</td>
        <td><strong>${escapeHtml(row.service)}</strong><small>${escapeHtml(row.source_sheet || row.source_type)}</small></td>
        <td class="numeric">${formatCurrency(row.realized_revenue)}</td>
        <td class="numeric">${formatCurrency(row.direct_cost)}</td>
        <td class="numeric finance-contribution">${formatCurrency(row.available_amount)}</td>
      `;
      body.appendChild(tr);
    });
  }

  $('#finance-details-realized').textContent = formatCurrency(data.totals.realized_revenue);
  $('#finance-details-costs').textContent = formatCurrency(data.totals.matched_direct_costs);
  $('#finance-details-service-total').textContent = formatCurrency(data.totals.service_available);
  $('#finance-details-unmatched').textContent = formatCurrency(data.deductions.unmatched_direct_costs);
  $('#finance-details-expenses').textContent = formatCurrency(data.deductions.operating_expenses);
  $('#finance-details-fixed-expenses').textContent = formatCurrency(data.deductions.fixed_expenses || 0);
  $('#finance-details-refunds').textContent = formatCurrency(data.deductions.refunds);
  $('#finance-details-net').textContent = formatCurrency(data.totals.net_available);
  availableDetailsLoadedFor = periodKey;
}

async function loadFixedExpenseDetails(force = false) {
  const from = $('#finance-from').value;
  const to = $('#finance-to').value;
  const basis = $('#finance-basis').value || 'cash_estimated';
  const periodKey = `${from}|${to}|${basis}`;
  if (!force && fixedExpenseDetailsLoadedFor === periodKey) return;

  const body = $('#finance-fixed-details-body');
  body.innerHTML = '<tr><td colspan="6">Cargando gastos fijos...</td></tr>';

  const data = await api(`/api/v1/finance/fixed-expense-details?from=${from}&to=${to}&basis=${basis}`);
  body.innerHTML = '';

  if (!data.items.length) {
    body.innerHTML = '<tr><td colspan="6" class="finance-details-empty">No hay gastos fijos confirmados en este período.</td></tr>';
  } else {
    data.items.forEach(row => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${formatDate(row.occurred_on)}</td>
        <td><strong>${escapeHtml(row.concept)}</strong><small>${escapeHtml(row.source_sheet || '')}${row.source_row ? ` · fila ${row.source_row}` : ''}</small></td>
        <td>${escapeHtml(row.category)}</td>
        <td>${escapeHtml(row.periodicity)}</td>
        <td>${escapeHtml(row.notes || '')}</td>
        <td class="numeric">${formatCurrency(row.amount)}</td>
      `;
      body.appendChild(tr);
    });
  }

  $('#finance-fixed-details-total').textContent = formatCurrency(data.total || 0);
  fixedExpenseDetailsLoadedFor = periodKey;
}

function escapeHtml(value) {
  const span = document.createElement('span');
  span.textContent = value ?? '';
  return span.innerHTML;
}

export function initFinanceDetails() {
  ensureFinancePeriodControls();

  const toggle = $('#finance-details-toggle');
  const details = $('#finance-available-details');
  const close = $('#finance-details-close');
  const monthSelect = $('#finance-month');
  const rangeToggle = $('#finance-use-range');
  const fixedCard = $('#finance-fixed-expenses-card');
  const fixedToggle = $('#finance-fixed-details-toggle');
  const fixedDetails = $('#finance-fixed-expense-details');
  const fixedClose = $('#finance-fixed-details-close');

  if (toggle && toggle.dataset.bound !== 'true') {
    toggle.dataset.bound = 'true';

    toggle.addEventListener('click', async () => {
      const willOpen = details.classList.contains('hidden');
      details.classList.toggle('hidden', !willOpen);
      toggle.setAttribute('aria-expanded', String(willOpen));
      toggle.textContent = willOpen ? 'Ocultar detalle' : 'Ver detalle por servicio';
      if (!willOpen) return;

      try {
        await loadAvailableDetails();
        details.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        notify('No se pudo cargar el detalle del disponible: ' + error.message, 'error');
      }
    });

    close.addEventListener('click', () => {
      details.classList.add('hidden');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.textContent = 'Ver detalle por servicio';
      toggle.focus();
    });
  }

  if (fixedCard && fixedCard.dataset.bound !== 'true') {
    fixedCard.dataset.bound = 'true';
    const openFixedDetails = async () => {
      const willOpen = fixedDetails.classList.contains('hidden');
      fixedDetails.classList.toggle('hidden', !willOpen);
      fixedCard.setAttribute('aria-expanded', String(willOpen));
      fixedToggle.setAttribute('aria-expanded', String(willOpen));
      fixedToggle.textContent = willOpen ? 'Ocultar detalle' : 'Ver detalle';
      if (!willOpen) return;

      try {
        await loadFixedExpenseDetails();
        fixedDetails.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        notify('No se pudo cargar el detalle de gastos fijos: ' + error.message, 'error');
      }
    };

    fixedCard.addEventListener('click', (event) => {
      event.preventDefault();
      openFixedDetails();
    });
    fixedCard.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openFixedDetails();
      }
    });
    fixedClose.addEventListener('click', () => {
      fixedDetails.classList.add('hidden');
      fixedCard.setAttribute('aria-expanded', 'false');
      fixedToggle.setAttribute('aria-expanded', 'false');
      fixedToggle.textContent = 'Ver detalle';
      fixedCard.focus();
    });
  }

  if (monthSelect.dataset.bound !== 'true') {
    monthSelect.dataset.bound = 'true';
    monthSelect.addEventListener('change', () => {
      applyFinancePeriodMode();
      loadFinanceDashboard();
    });
  }

  if (rangeToggle.dataset.bound !== 'true') {
    rangeToggle.dataset.bound = 'true';
    rangeToggle.addEventListener('change', () => {
      applyFinancePeriodMode();
      loadFinanceDashboard();
    });
  }
}

function updateFinanceChart(metrics) {
  const ctx = document.getElementById('finance-chart');
  if (!ctx) return;

  if (financeChartInstance) {
    financeChartInstance.destroy();
  }

  const sales = Number(metrics.contracted_sales || 0);
  const collected = Number(metrics.collected_revenue || 0);
  const egresos = Number(metrics.direct_costs || 0) + Number(metrics.fixed_expenses || 0) + Number(metrics.operating_expenses || 0);
  const net = Number(metrics.net_result || 0);

  // Check if Chart is loaded
  if (typeof Chart === 'undefined') {
    console.warn('Chart.js not loaded yet');
    return;
  }

  financeChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Vendido', 'Recibido', 'Egresos', 'Disponible'],
      datasets: [{
        data: [sales, collected, egresos, net],
        backgroundColor: [
          'rgba(6, 182, 212, 0.6)',  // cyan
          'rgba(16, 185, 129, 0.6)', // green (success)
          'rgba(239, 68, 68, 0.6)',   // red (danger)
          'rgba(14, 116, 144, 0.75)'  // dark cyan/teal
        ],
        borderColor: [
          'rgb(6, 182, 212)',
          'rgb(16, 185, 129)',
          'rgb(239, 68, 68)',
          'rgb(14, 116, 144)'
        ],
        borderWidth: 1.5,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return ' ' + new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(context.raw);
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return '$' + Number(value).toLocaleString('es-CL');
            }
          }
        }
      }
    }
  });
}

export async function loadFinanceDashboard() {
  ensureFinancePeriodControls();
  const fromEl = $('#finance-from');
  const toEl = $('#finance-to');
  applyFinancePeriodMode();

  const basis = $('#finance-basis').value || 'cash_estimated';
  const from = fromEl.value;
  const to = toEl.value;
  const rangeMode = isRangeMode();

  try {
    $('#finance-quality-indicator').innerHTML = 'Cargando métricas...';
    
    const data = await api(`/api/v1/finance/dashboard?from=${from}&to=${to}&basis=${basis}`);

    // Update Metrics
    const formatPercent = (val) => val === null ? '--' : new Intl.NumberFormat('es-CL', { style: 'percent', minimumFractionDigits: 1 }).format(val);

    $('#finance-val-contracted').textContent = formatCurrency(data.metrics.contracted_sales);
    $('#finance-val-collected').textContent = formatCurrency(data.metrics.collected_revenue);
    $('#finance-val-committed').textContent = formatCurrency(data.metrics.committed_deposits);
    const releasedRevenue = Math.min(
      Number(data.metrics.collected_revenue || 0),
      Number(data.metrics.realized_revenue || 0)
    );
    $('#finance-val-released').textContent = formatCurrency(releasedRevenue);
    $('#finance-val-receivable').textContent = formatCurrency(data.metrics.accounts_receivable);
    $('#finance-val-realized').textContent = formatCurrency(data.metrics.realized_revenue);
    $('#finance-val-costs').textContent = formatCurrency(data.metrics.direct_costs);
    $('#finance-val-expenses').textContent = formatCurrency(data.metrics.operating_expenses);
    $('#finance-val-fixed-expenses').textContent = formatCurrency(data.metrics.fixed_expenses || 0);
    const pendingFixed = Number(data.quality.fixed_expenses_pending_count || 0);
    $('#finance-fixed-expense-status').textContent = pendingFixed > 0
      ? `${pendingFixed} partidas pendientes de monto`
      : 'Todos los montos están confirmados';
    $('#finance-val-refunds').textContent = formatCurrency(data.metrics.refunds);
    $('#finance-val-net').textContent = formatCurrency(data.metrics.net_result);
    $('#finance-val-margin').textContent = formatPercent(data.metrics.operating_margin);
    availableDetailsLoadedFor = '';
    fixedExpenseDetailsLoadedFor = '';
    if (!$('#finance-available-details').classList.contains('hidden')) {
      await loadAvailableDetails(true);
    }
    if (!$('#finance-fixed-expense-details').classList.contains('hidden')) {
      await loadFixedExpenseDetails(true);
    }

    const received = Number(data.metrics.collected_revenue || 0);
    const pending = Number(data.metrics.accounts_receivable || 0);
    const available = Number(data.metrics.net_result || 0);
    const committed = Number(data.metrics.committed_deposits || 0);
    const realized = Number(data.metrics.realized_revenue || 0);
    const fixedExpenses = Number(data.metrics.fixed_expenses || 0);
    const nonFixedDeductions = Number(data.metrics.direct_costs || 0)
      + Number(data.metrics.operating_expenses || 0)
      + Number(data.metrics.refunds || 0);
    const amountTowardFixed = Math.max(0, realized - nonFixedDeductions);
    const missingFixed = Math.max(0, fixedExpenses - amountTowardFixed);

    if (!rangeMode && fixedExpenses > 0) {
      $('#finance-story-title').textContent = missingFixed > 0
        ? `Nos falta ${formatCurrency(missingFixed)} para cubrir el gasto fijo de este mes.`
        : `Gasto fijo cubierto; ${formatCurrency(available)} queda disponible para caja.`;
    } else {
      $('#finance-story-title').textContent = committed > 0
        ? `Ingresaron ${formatCurrency(received)} en el período; ${formatCurrency(releasedRevenue)} ya están liberados y ${formatCurrency(committed)} siguen retenidos como reservas abiertas.`
        : `Ingresaron ${formatCurrency(received)}, sin abonos retenidos.`;
    }

    const partes = [];
    if (!rangeMode && fixedExpenses > 0) partes.push(`${formatCurrency(amountTowardFixed)} cubiertos de ${formatCurrency(fixedExpenses)} en gastos fijos`);
    if (committed > 0) partes.push(`${formatCurrency(committed)} corresponden a reservas de servicios que aún no se realizan y se liberan al terminarlos`);
    if (pending > 0) partes.push(`${formatCurrency(pending)} siguen pendientes de cobro`);
    partes.push(`el resultado del período es ${formatCurrency(available)}`);
    $('#finance-story-copy').textContent = `${partes.join('; ')}.`;

    // Render Chart
    updateFinanceChart(data.metrics);

    // Update Quality Indicator
    const q = data.quality;
    const qualityIndicator = $('#finance-quality-indicator');
    const audit = $('#finance-audit');
    qualityIndicator.classList.remove('is-ok', 'needs-review');
    if (q.is_reconciled) {
      qualityIndicator.textContent = 'Todo cuadra';
      qualityIndicator.classList.add('is-ok');
      audit.open = false;
    } else {
      qualityIndicator.textContent = 'Requiere revisión';
      qualityIndicator.classList.add('needs-review');
      audit.open = true;
    }

    // Update Reconciliation Table
    const rec = data.reconciliation;
    const body = $('#finance-reconciliation-body');
    body.innerHTML = '';
    
    const categories = [
      { key: 'service_revenue', label: 'Ventas de Servicios' },
      { key: 'customer_payment', label: 'Pagos de Clientes' },
      { key: 'direct_cost', label: 'Costos Directos' },
      { key: 'operational_expense', label: 'Gastos Operativos' },
      { key: 'fixed_expense', label: 'Gastos Fijos' },
      { key: 'refund', label: 'Devoluciones' },
    ];

    categories.forEach(c => {
      const row = rec[c.key] || { sheet_total: 0, projected_total: 0, difference: 0, excluded_rows: 0 };
      const isOk = Number(row.difference) === 0;
      
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${c.label}</td>
        <td class="numeric">${formatCurrency(row.sheet_total)}</td>
        <td class="numeric">${formatCurrency(row.projected_total)}</td>
        <td class="numeric ${isOk ? 'reconciliation-ok' : 'reconciliation-review'}">
          ${formatCurrency(row.difference)}
        </td>
        <td class="numeric">${row.excluded_rows}</td>
        <td>
          <span class="status-badge ${isOk ? 'ok' : 'warning'}">${isOk ? 'Cuadra' : 'Revisar'}</span>
        </td>
      `;
      body.appendChild(tr);
    });

  } catch (e) {
    notify('Error cargando el dashboard financiero: ' + e.message, 'error');
    $('#finance-quality-indicator').textContent = 'No se pudo comprobar';
    $('#finance-quality-indicator').className = 'finance-quality needs-review';
    $('#finance-story-title').textContent = 'No pudimos cargar el resumen financiero.';
    $('#finance-story-copy').textContent = 'Revisa la conexión y vuelve a actualizar.';
  }
}

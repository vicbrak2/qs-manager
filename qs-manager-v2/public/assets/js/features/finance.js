import { api } from '../api.js';
import { $ } from '../dom.js';
import { notify } from '../ui/notifications.js';

let financeChartInstance = null;
let availableDetailsLoadedFor = '';

const formatCurrency = (val) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(val);

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

function escapeHtml(value) {
  const span = document.createElement('span');
  span.textContent = value ?? '';
  return span.innerHTML;
}

export function initFinanceDetails() {
  const toggle = $('#finance-details-toggle');
  const details = $('#finance-available-details');
  const close = $('#finance-details-close');
  if (!toggle || toggle.dataset.bound === 'true') return;
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
  const fromEl = $('#finance-from');
  const toEl = $('#finance-to');
  
  if (!fromEl.value || !toEl.value) {
    const now = new Date();
    // Default to "Este mes" in America/Santiago logical terms (approx local time)
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
    
    fromEl.value = `${y}-${m}-01`;
    toEl.value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
  }

  const basis = $('#finance-basis').value || 'cash_estimated';
  const from = fromEl.value;
  const to = toEl.value;

  try {
    $('#finance-quality-indicator').innerHTML = 'Cargando métricas...';
    
    const data = await api(`/api/v1/finance/dashboard?from=${from}&to=${to}&basis=${basis}`);

    // Update Metrics
    const formatPercent = (val) => val === null ? '--' : new Intl.NumberFormat('es-CL', { style: 'percent', minimumFractionDigits: 1 }).format(val);

    $('#finance-val-contracted').textContent = formatCurrency(data.metrics.contracted_sales);
    $('#finance-val-collected').textContent = formatCurrency(data.metrics.collected_revenue);
    $('#finance-val-committed').textContent = formatCurrency(data.metrics.committed_deposits);
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
    if (!$('#finance-available-details').classList.contains('hidden')) {
      await loadAvailableDetails(true);
    }

    const received = Number(data.metrics.collected_revenue || 0);
    const pending = Number(data.metrics.accounts_receivable || 0);
    const available = Number(data.metrics.net_result || 0);
    const committed = Number(data.metrics.committed_deposits || 0);
    $('#finance-story-title').textContent = `Ingresaron ${formatCurrency(received)}; ${formatCurrency(available)} corresponden a utilidad realizada.`;
    $('#finance-story-copy').textContent = pending > 0
      ? `${formatCurrency(committed)} están comprometidos en servicios futuros y ${formatCurrency(pending)} siguen pendientes de cobro.`
      : `${formatCurrency(committed)} están comprometidos en servicios futuros.`;

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

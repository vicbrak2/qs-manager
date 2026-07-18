import { api } from '../api.js';
import { $ } from '../dom.js';
import { notify } from '../ui/notifications.js';

let financeChartInstance = null;

function updateFinanceChart(metrics) {
  const ctx = document.getElementById('finance-chart');
  if (!ctx) return;

  if (financeChartInstance) {
    financeChartInstance.destroy();
  }

  const sales = Number(metrics.contracted_sales || 0);
  const collected = Number(metrics.collected_revenue || 0);
  const egresos = Number(metrics.direct_costs || 0) + Number(metrics.operating_expenses || 0);
  const net = Number(metrics.net_result || 0);

  // Check if Chart is loaded
  if (typeof Chart === 'undefined') {
    console.warn('Chart.js not loaded yet');
    return;
  }

  financeChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Vendido', 'Cobrado', 'Egresos', 'Utilidad Neta'],
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
    const formatCurrency = (val) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(val);
    const formatPercent = (val) => val === null ? '--' : new Intl.NumberFormat('es-CL', { style: 'percent', minimumFractionDigits: 1 }).format(val);

    $('#finance-val-contracted').textContent = formatCurrency(data.metrics.contracted_sales);
    $('#finance-val-collected').textContent = formatCurrency(data.metrics.collected_revenue);
    $('#finance-val-receivable').textContent = formatCurrency(data.metrics.accounts_receivable);
    $('#finance-val-costs').textContent = formatCurrency(data.metrics.direct_costs);
    $('#finance-val-expenses').textContent = formatCurrency(data.metrics.operating_expenses);
    $('#finance-val-refunds').textContent = formatCurrency(data.metrics.refunds);
    $('#finance-val-net').textContent = formatCurrency(data.metrics.net_result);
    $('#finance-val-margin').textContent = formatPercent(data.metrics.operating_margin);

    // Render Chart
    updateFinanceChart(data.metrics);

    // Update Quality Indicator
    const q = data.quality;
    if (q.is_reconciled) {
      $('#finance-quality-indicator').innerHTML = `<span style="color: var(--color-success-600); font-weight: 600;">✓ Reconciliado</span> (Filas sin ID: ${q.missing_external_ids})`;
    } else {
      $('#finance-quality-indicator').innerHTML = `<span style="color: var(--color-danger-600); font-weight: 600;">⚠ Descuadre Detectado</span>`;
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
      { key: 'refund', label: 'Devoluciones' },
    ];

    categories.forEach(c => {
      const row = rec[c.key];
      const isOk = row.difference === 0;
      
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${c.label}</td>
        <td style="text-align: right;">${formatCurrency(row.sheet_total)}</td>
        <td style="text-align: right;">${formatCurrency(row.projected_total)}</td>
        <td style="text-align: right; color: ${isOk ? 'var(--color-success-600)' : 'var(--color-danger-600)'}; font-weight: ${isOk ? 'normal' : '600'};">
          ${formatCurrency(row.difference)}
        </td>
        <td style="text-align: right; color: var(--text-muted);">${row.excluded_rows}</td>
        <td style="text-align: center;">
          <span class="status-badge ${isOk ? 'ok' : 'error'}">${isOk ? 'OK' : 'FAIL'}</span>
        </td>
      `;
      body.appendChild(tr);
    });

  } catch (e) {
    notify('Error cargando el dashboard financiero: ' + e.message, 'error');
    $('#finance-quality-indicator').innerHTML = `<span style="color: var(--color-danger-600);">Error de carga</span>`;
  }
}

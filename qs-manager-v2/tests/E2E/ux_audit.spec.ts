import { test, expect } from '@playwright/test';

test.describe('Auditoría Automatizada de Usabilidad y UX', () => {
  const bookingsFixture = {
    bookings: [
      {
        id: 501,
        service_id: null,
        staff_id: null,
        customer_name: 'Verónica Silva',
        customer_phone: '+56911111111',
        scheduled_for: '2026-08-15T14:00:00+00:00',
        status: 'confirmed',
        service_name: 'Novia Fiesta Maquillaje',
        staff_name: null,
        address: 'Av. Providencia 123',
        comuna: 'Providencia',
        total_service: 120000,
        balance_due: 40000,
        payment_status: 'parcial',
        source_sheet: null,
        source_row: null,
        bitacora_id: null,
        gas_last_sync_status: null,
        gas_last_sync_message: null,
      },
      {
        id: 502,
        service_id: null,
        staff_id: null,
        customer_name: 'Camila Soto',
        customer_phone: '+56922222222',
        scheduled_for: '2026-09-20T10:00:00+00:00',
        status: 'draft',
        service_name: 'Social Peinado',
        staff_name: null,
        address: 'Las Condes 456',
        comuna: 'Las Condes',
        total_service: 85000,
        balance_due: 85000,
        payment_status: 'pendiente',
        source_sheet: null,
        source_row: null,
        bitacora_id: 7,
        gas_last_sync_status: null,
        gas_last_sync_message: null,
      },
    ],
  };

  test('Escenario 1: Dashboard y Gráfico de Finanzas (Carga y Responsividad)', async ({ page }) => {
    // Interceptar la API de finanzas para devolver datos consistentes de prueba
    await page.route('/api/v1/finance/dashboard*', async (route) => {
      const json = {
        period: { from: '2026-07-01', to: '2026-07-31', basis: 'cash_estimated' },
        metrics: {
          contracted_sales: 45000,
          collected_revenue: 105000,
          committed_deposits: 0,
          realized_revenue: 105000,
          accounts_receivable: 0,
          direct_costs: 0,
          operating_expenses: 0,
          refunds: 0,
          net_result: 105000,
          operating_margin: 1.0
        },
        reconciliation: {
          service_revenue: { sheet_total: 0, projected_total: 45000, difference: -45000, excluded_rows: 1 },
          customer_payment: { sheet_total: 60000, projected_total: 105000, difference: -45000, excluded_rows: 0 },
          direct_cost: { sheet_total: 0, projected_total: 0, difference: 0, excluded_rows: 0 },
          operational_expense: { sheet_total: 0, projected_total: 0, difference: 0, excluded_rows: 0 },
          refund: { sheet_total: 0, projected_total: 0, difference: 0, excluded_rows: 0 }
        },
        quality: {
          is_reconciled: false,
          missing_external_ids: 0,
          estimated_cost_rows: 0
        }
      };
      await route.fulfill({ json });
    });

    await page.goto('/');
    await page.waitForSelector('#finance-val-contracted');

    // 1. Carga automática y fechas
    const contractedVal = await page.locator('#finance-val-contracted').textContent();
    expect(contractedVal).toContain('$');
    expect(contractedVal).toContain('45.000');
    
    const fromVal = await page.locator('#finance-from').inputValue();
    const toVal = await page.locator('#finance-to').inputValue();
    expect(fromVal).toMatch(/^\d{4}-\d{2}-01$/); // Primer día del mes
    expect(toVal).toMatch(/^\d{4}-\d{2}-\d{2}$/); // Último día del mes

    // 2. Gráfico interactivo visible
    const canvas = page.locator('#finance-chart');
    await expect(canvas).toBeVisible();
    
    // 3. Responsividad en pantalla móvil (<1024px)
    await page.setViewportSize({ width: 800, height: 800 });
    await page.waitForTimeout(500);
    
    // .finance-grid ya no existe -- el dashboard se rediseño a
    // .finance-dashboard-row > .finance-main + .chart-panel, que es lo que
    // realmente se apila en mobile (ver main.css @media max-width: 1024px).
    const gridBox = await page.locator('.finance-main').boundingBox();
    const chartBox = await page.locator('.chart-panel').boundingBox();
    
    expect(gridBox).not.toBeNull();
    expect(chartBox).not.toBeNull();
    
    if (gridBox && chartBox) {
      // El gráfico debe estar posicionado ABAJO de la grilla de métricas en pantallas móviles
      expect(chartBox.y).toBeGreaterThanOrEqual(gridBox.y + gridBox.height);
    }
  });

  test('Escenario 2: Listados y Filtros (Reservas)', async ({ page }) => {
    await page.route('/api/v1/bookings*', (route) => route.fulfill({ json: bookingsFixture }));

    await page.goto('/');
    await page.waitForSelector('#tab-bookings');
    
    // Ir a la pestaña Reservas
    await page.click('#tab-bookings');
    await page.waitForSelector('#bookings-body');

    // 1. Buscar en tiempo real
    const initialRowsCount = await page.locator('#bookings-body tr').count();
    
    await page.fill('#booking-filter-text', 'Verónica');
    await page.waitForTimeout(500); // esperar filtrado local
    
    const filteredRowsCount = await page.locator('#bookings-body tr').count();
    expect(filteredRowsCount).toBeLessThanOrEqual(initialRowsCount);

    // Limpiar filtro de búsqueda
    await page.fill('#booking-filter-text', '');
    await page.waitForTimeout(500);

    // 2. Ordenamiento de Datos
    const firstRowDateBefore = await page.locator('#bookings-body tr:first-child td:nth-child(2)').textContent();
    await page.click('.sort-btn[data-booking-sort="scheduled_for"]');
    await page.waitForTimeout(500);
    const firstRowDateAfter = await page.locator('#bookings-body tr:first-child td:nth-child(2)').textContent();
    expect(firstRowDateBefore).not.toBe(firstRowDateAfter); // La fecha debe haber cambiado por el ordenamiento
  });

  test('Escenario 3: Formularios, Validaciones y Limpieza', async ({ page }) => {
    // Interceptar la API de creación de reservas para devolver un error de validación 422
    await page.route('/api/v1/bookings', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 422,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 'Validation failed',
            errors: {
              customer_name: ['El nombre de la clienta es obligatorio.'],
              customer_phone: ['El formato del teléfono es inválido.']
            }
          })
        });
      } else {
        await route.fulfill({ json: bookingsFixture });
      }
    });

    await page.goto('/');
    await page.waitForSelector('#tab-bookings');

    // Ir a Reservas y abrir formulario
    await page.click('#tab-bookings');
    await page.waitForSelector('#bookings-body');
    
    // 1. Validaciones al guardar con errores simulados
    await page.click('#save-booking');
    
    // Verificar bordes y error text
    const clientInput = page.locator('#booking-form [name=customer_name]');
    await expect(clientInput).toHaveClass(/invalid-field/);
    
    const errorText = page.locator('#booking-form .error-helper-text').first();
    await expect(errorText).toBeVisible();
    await expect(errorText).toHaveText('El nombre de la clienta es obligatorio.');

    // 2. Limpieza del Formulario
    await page.click('#reset-booking');
    const clientValue = await clientInput.inputValue();
    expect(clientValue).toBe('');
    
    const formTitle = await page.locator('#booking-form-title').textContent();
    expect(formTitle).toBe('Nueva reserva');
  });

});

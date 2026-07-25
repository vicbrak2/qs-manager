import { test, expect } from '@playwright/test';

test.describe('Bitácora y disponibilidad de staff', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('/health', (route) => route.fulfill({ json: { status: 'ok', database: 'ok' } }));
    await page.route('/api/v1/sync/sheets/status', (route) => route.fulfill({
      json: { enabled: false, sources: [] },
    }));
    await page.route('/api/v1/services*', (route) => route.fulfill({
      json: { services: [{ id: 7, name: 'Novia', category: null, quantity: 1, duration_minutes: 90, sale_price: 100000, total_cost: null, utility: null, margin_percent: null, margin_status: null, active: true, source_sheet: null, source_row: null }] },
    }));
    await page.route('/api/v1/team*', (route) => route.fulfill({
      json: { staff: [{ id: 3, display_name: 'Fernanda Rojas', role: 'staff', active: true }] },
    }));
    await page.route('/api/v1/bookings*', (route) => route.fulfill({ json: { bookings: [] } }));
    await page.route('/api/v1/bitacoras', (route) => route.fulfill({
      json: { bitacoras: [{
        id: 1,
        fecha_servicio: '2026-09-12',
        tipo_servicio: 'Novia',
        mua_id: 3,
        estilista_id: null,
        clienta_nombre: 'Camila Soto',
        direccion_servicio: 'Av. Siempre Viva 123, Buin',
        route_plan: {
          pickup_point: 'Estudio Qamiluna',
          pickup_order: null,
          travel_duration_min: 45,
          recommended_minimum_met: true,
          arrival_time: '07:30',
        },
        notas_logisticas: null,
        costo_staff_clp: 60000,
        precio_cliente_clp: 100000,
        projected_margin_clp: 40000,
        notes: [{ message: 'Esperar en la reja principal', author_user_id: null, created_at: '2026-07-25T12:00:00+00:00' }],
        created_at: '2026-07-25T12:00:00+00:00',
        updated_at: '2026-07-25T12:00:00+00:00',
      }] },
    }));
  });

  test('la pestaña Bitácora lista registros y el editor muestra las notas', async ({ page }) => {
    await page.goto('/');
    await page.click('#tab-bitacora');
    await expect(page.locator('#bitacora-view')).not.toHaveClass(/hidden/);

    const row = page.locator('#bitacoras-body tr').first();
    await expect(row).toContainText('Camila Soto');
    await expect(row).toContainText('Fernanda Rojas');
    await expect(row).toContainText('45 min');
    await expect(row).toContainText('40.000'); // margen proyectado

    await page.click('[data-edit-bitacora="1"]');
    await expect(page.locator('#bitacora-form-title')).toHaveText('Bitácora #1');
    await expect(page.locator('#bitacora-form [name=clienta_nombre]')).toHaveValue('Camila Soto');
    await expect(page.locator('#bitacora-form [name=punto_salida]')).toHaveValue('Estudio Qamiluna');
    await expect(page.locator('#bitacora-notes-panel')).not.toHaveClass(/hidden/);
    await expect(page.locator('#bitacora-notes-list')).toContainText('Esperar en la reja principal');
  });

  test('el form de reservas avisa los bloques ocupados del staff elegido', async ({ page }) => {
    await page.route('**/api/v1/team/3/availability*', (route) => route.fulfill({
      json: {
        staff_id: 3,
        date: '2026-08-15',
        requested_time: '10:30',
        available: false,
        busy: [{
          start_at: '2026-08-15T10:00:00+00:00',
          end_at: '2026-08-15T11:30:00+00:00',
          label: 'Novia — Camila Soto',
        }],
      },
    }));

    await page.goto('/');
    await page.click('#tab-bookings');

    await page.selectOption('#booking-staff-select', '3');
    await page.fill('#booking-form [name=scheduled_for]', '2026-08-15T10:30');

    const hint = page.locator('#booking-availability');
    await expect(hint).not.toHaveClass(/hidden/);
    await expect(hint).toContainText('choca con una reserva existente');
    await expect(hint.locator('.badge')).toHaveCount(1);
    await expect(hint.locator('.badge')).toHaveAttribute('title', 'Novia — Camila Soto');
  });
});

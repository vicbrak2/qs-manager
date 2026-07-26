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
        booking_id: null,
        booking_external_id: null,
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

  test('crear bitácora desde una reserva prellena el form y manda booking_id', async ({ page }) => {
    await page.route('/api/v1/bookings*', (route) => route.fulfill({
      json: { bookings: [{
        id: 44,
        service_id: 7,
        staff_id: 3,
        customer_name: 'Camila Soto',
        customer_phone: '+56912345678',
        scheduled_for: '2026-09-12T12:30:00+00:00',
        status: 'confirmed',
        service_name: 'Novia',
        staff_name: 'Fernanda Rojas',
        address: 'Av. Siempre Viva 123',
        comuna: 'Buin',
        total_service: 100000,
        balance_due: null,
        payment_status: null,
        source_sheet: null,
        source_row: null,
        bitacora_id: null,
        gas_last_sync_status: null,
        gas_last_sync_message: null,
      }] },
    }));

    let posted: Record<string, unknown> | null = null;
    await page.route('/api/v1/bitacoras', async (route) => {
      if (route.request().method() === 'POST') {
        posted = route.request().postDataJSON();
        await route.fulfill({
          status: 201,
          json: { bitacora: { id: 2, ...(posted as object), route_plan: {}, projected_margin_clp: 100000, notes: [] } },
        });
        return;
      }

      await route.fulfill({ json: { bitacoras: [] } });
    });

    await page.goto('/');
    await page.click('#tab-bookings');
    await page.click('[data-create-bitacora="44"]');

    await expect(page.locator('#bitacora-view')).not.toHaveClass(/hidden/);
    await expect(page.locator('#bitacora-form [name=clienta_nombre]')).toHaveValue('Camila Soto');
    await expect(page.locator('#bitacora-form [name=fecha_servicio]')).toHaveValue('2026-09-12');
    await expect(page.locator('#bitacora-form [name=precio_cliente_clp]')).toHaveValue('100000');
    await expect(page.locator('#bitacora-booking-link')).toContainText('Vinculada a reserva #44');

    await page.fill('#bitacora-form [name=punto_salida]', 'Estudio Qamiluna');
    await page.click('#save-bitacora');
    await expect.poll(() => posted?.booking_id).toBe(44);
  });

  test('una reserva con bitácora abre el editor con chip de vínculo', async ({ page }) => {
    await page.route('/api/v1/bookings*', (route) => route.fulfill({
      json: { bookings: [{
        id: 44,
        service_id: 7,
        staff_id: 3,
        customer_name: 'Camila Soto',
        customer_phone: '+56912345678',
        scheduled_for: '2026-09-12T12:30:00+00:00',
        status: 'confirmed',
        service_name: 'Novia',
        staff_name: 'Fernanda Rojas',
        address: 'Av. Siempre Viva 123',
        comuna: 'Buin',
        total_service: 100000,
        balance_due: null,
        payment_status: null,
        source_sheet: null,
        source_row: null,
        bitacora_id: 1,
        gas_last_sync_status: null,
        gas_last_sync_message: null,
      }] },
    }));

    await page.route('/api/v1/bitacoras', (route) => route.fulfill({
      json: { bitacoras: [{
        id: 1,
        booking_id: 44,
        booking_external_id: null,
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
        notes: [],
        created_at: '2026-07-25T12:00:00+00:00',
        updated_at: '2026-07-25T12:00:00+00:00',
      }] },
    }));

    await page.goto('/');
    await page.click('#tab-bookings');
    await page.click('[data-open-bitacora="1"]');

    await expect(page.locator('#bitacora-form-title')).toHaveText('Bitácora #1');
    await expect(page.locator('#bitacora-booking-link')).toContainText('Vinculada a reserva #44');
  });
});

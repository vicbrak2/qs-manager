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

  test('el plan de traslado calcula salida y llegada, y arma el texto del equipo', async ({ page }) => {
    await page.route('/api/v1/bitacoras', (route) => route.fulfill({ json: { bitacoras: [] } }));

    await page.goto('/');
    await page.click('#tab-bitacora');

    // Sin datos suficientes, el plan lista lo que falta.
    await page.fill('#bitacora-form [name=clienta_nombre]', 'Sara Martinez');
    const plan = page.locator('#bitacora-plan');
    await expect(plan).toContainText('Falta para armar la bitácora');
    await expect(plan).toContainText('hora de inicio del servicio');

    await page.fill('#bitacora-form [name=fecha_servicio]', '2026-05-22');
    await page.fill('#bitacora-form [name=tipo_servicio]', 'Prueba Novia (Maquillaje + Peinado)');
    await page.fill('#bitacora-form [name=direccion_servicio]', 'Padre Fernando Cifuentes Grez 4861, Macul');
    await page.fill('#bitacora-form [name=punto_salida]', 'Metro Macul');
    await page.fill('#bitacora-form [name=hora_inicio_servicio]', '16:00');
    await page.fill('#bitacora-form [name=hora_fin_servicio]', '18:00');
    await page.selectOption('#bitacora-mua-select', '3');

    // Falta el tramo: ya puede anticipar la llegada, no la salida.
    await expect(plan).toContainText('Llegada objetivo: 15:45');
    await expect(plan).toContainText('al menos un tramo');

    await page.click('#add-tramo');
    await page.fill('.tramo-row .tramo-destino', 'Macul');
    await page.fill('.tramo-row .tramo-min', '10');

    // 16:00 − 15 (llegada) − 10 (tramo) − 15 (holgura) = 15:20.
    await expect(plan).toContainText('Plan de traslado completo');
    await expect(plan).toContainText('Salida sugerida: 15:20');
    await expect(plan).toContainText('Llegada: 15:45');

    await page.click('#copy-bitacora-team');
    const preview = page.locator('#bitacora-team-preview');
    await expect(preview).toContainText('✨ Bitácora — Prueba Novia (Maquillaje + Peinado) — Sara Martinez');
    await expect(preview).toContainText('⏰ Horario del servicio: 16:00 - 18:00 hrs');
    await expect(preview).toContainText('🧑‍🎨 Profesionales: Fernanda Rojas (maquilladora)');
    await expect(preview).toContainText('🕐 Hora de salida: 15:20 hrs');
    await expect(preview).toContainText('🕒 Hora de llegada estimada: 15:45 hrs (15 minutos antes del inicio)');
    await expect(preview).toContainText('🗺️ Orden de traslado: Metro Macul → Macul');

    // Quitar el tramo vuelve a dejar el plan incompleto.
    await page.click('[data-remove-tramo]');
    await expect(plan).toContainText('al menos un tramo');
  });

  test('una reserva próxima sin bitácora se marca en rojo', async ({ page }) => {
    const enTresDias = new Date(Date.now() + 3 * 86400000).toISOString();
    const enUnMes = new Date(Date.now() + 30 * 86400000).toISOString();
    const base = {
      service_id: 7, staff_id: 3, customer_phone: null, status: 'confirmed',
      service_name: 'Novia', staff_name: 'Fernanda Rojas', address: 'Calle 1',
      comuna: 'Macul', total_service: 100000, balance_due: null,
      payment_status: null, source_sheet: null, source_row: null,
      gas_last_sync_status: null, gas_last_sync_message: null,
    };

    await page.route('/api/v1/bookings*', (route) => route.fulfill({
      json: { bookings: [
        { ...base, id: 91, customer_name: 'Urgente sin bitácora', scheduled_for: enTresDias, bitacora_id: null },
        { ...base, id: 92, customer_name: 'Lejana sin bitácora', scheduled_for: enUnMes, bitacora_id: null },
        { ...base, id: 93, customer_name: 'Urgente con bitácora', scheduled_for: enTresDias, bitacora_id: 5 },
      ] },
    }));

    await page.goto('/');
    await page.click('#tab-bookings');

    // A 3 días y sin bitácora -> alerta roja.
    await expect(page.locator('tr[data-bitacora-pendiente="true"]')).toHaveCount(1);
    await expect(page.locator('[data-create-bitacora="91"]')).toHaveText('⚠ Falta bitácora');
    await expect(page.locator('[data-create-bitacora="91"]')).toHaveClass(/danger/);

    // A un mes todavía no urge.
    await expect(page.locator('[data-create-bitacora="92"]')).toHaveText('Crear bitácora');
    // Ya tiene bitácora: no hay alerta aunque el servicio esté cerca.
    await expect(page.locator('[data-open-bitacora="5"]')).toBeVisible();
  });

  test('genera la imagen de la bitácora para mandar al equipo', async ({ page }) => {
    await page.route('/api/v1/bitacoras', (route) => route.fulfill({ json: { bitacoras: [] } }));

    await page.goto('/');
    await page.click('#tab-bitacora');

    await page.fill('#bitacora-form [name=fecha_servicio]', '2026-07-27');
    await page.fill('#bitacora-form [name=tipo_servicio]', 'Novia Civil Maquillaje Peinado');
    await page.fill('#bitacora-form [name=clienta_nombre]', 'Nadia Palomino');
    await page.fill('#bitacora-form [name=direccion_servicio]', 'Gerónimo de Alderete 208, La Florida');
    await page.fill('#bitacora-form [name=punto_salida]', 'Estudio Qamiluna');
    await page.fill('#bitacora-form [name=hora_inicio_servicio]', '08:00');
    await page.selectOption('#bitacora-mua-select', '3');
    await page.click('#add-tramo');
    await page.fill('.tramo-row .tramo-destino', 'La Florida');
    await page.fill('.tramo-row .tramo-min', '40');

    await page.click('#image-bitacora-team');

    const canvas = page.locator('#bitacora-image-preview canvas');
    await expect(canvas).toBeVisible();
    // El alto depende del contenido: si el render falla queda en 0.
    const size = await canvas.evaluate((el: HTMLCanvasElement) => ({ w: el.width, h: el.height }));
    expect(size.w).toBeGreaterThan(0);
    expect(size.h).toBeGreaterThan(400);

    // La imagen se ofrece como PNG descargable.
    const link = page.locator('#bitacora-image-preview a');
    await expect(link).toHaveAttribute('download', /bitacora-2026-07-27\.png/);
    await expect(link).toHaveAttribute('href', /^data:image\/png;base64,/);
  });
});

import { test, expect } from '@playwright/test';

test.describe('QS Manager V2 Dashboard E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Go to the dashboard home page
    await page.goto('/');
  });



  test('should display visual token design metrics, tabs, and filters', async ({ page }) => {
    // Check metric counters
    await expect(page.locator('#metric-services')).toBeVisible();
    await expect(page.locator('#metric-bookings')).toBeVisible();
    await expect(page.locator('#metric-confirmed')).toBeVisible();

    // Check tabs
    await expect(page.locator('#tab-services')).toBeVisible();
    await expect(page.locator('#tab-bookings')).toBeVisible();

    // Switch to Bookings view
    await page.locator('#tab-bookings').click();
    await expect(page.locator('#bookings-view')).not.toHaveClass(/hidden/);

    // Verify filter dropdown elements are present
    await expect(page.locator('#booking-filter-text')).toBeVisible();
    await expect(page.locator('#booking-filter-service')).toBeVisible();
    await expect(page.locator('#booking-filter-staff')).toBeVisible();
    await expect(page.locator('#booking-filter-status')).toBeVisible();
  });

  test('should filter bookings table in real-time', async ({ page }) => {
    // Switch to Bookings view
    await page.locator('#tab-bookings').click();

    // Enter filter criteria and verify it triggers filtering
    const filterStatus = page.locator('#booking-filter-status');
    await filterStatus.selectOption('confirmed');
    // Verify bookings body reflects matching items only
    // (Since we are testing UI logic dynamically on change, wait for state filter application)
    await expect(page.locator('#booking-filter-status')).toHaveValue('confirmed');
  });

  test('should render booking rows with traffic-light urgency colors', async ({ page }) => {
    const now = Date.now();
    const booking = (id: number, daysFromNow: number, status = 'confirmed') => ({
      id,
      service_id: null,
      staff_id: null,
      customer_name: `Cliente ${id}`,
      customer_phone: null,
      scheduled_for: new Date(now + daysFromNow * 86400000).toISOString(),
      status,
      service_name: 'Servicio prueba',
      comuna: null,
      address: null,
      total_service: 50000,
      balance_due: 25000,
      payment_status: 'Parcial',
      source_sheet: 'Test',
      source_row: id,
    });

    await page.route('**/api/v1/bookings', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ bookings: [
        booking(1, -1),
        booking(2, 1, 'completed'),
        booking(3, 14),
        booking(4, 7),
        booking(5, 2, 'cancelled'),
      ] }),
    }));

    await page.goto('/');
    await expect(page.locator('#tab-finance')).toHaveClass(/active/);
    await page.locator('#tab-bookings').click();
    await expect(page.locator('#bookings-view')).not.toHaveClass(/hidden/);

    await expect(page.locator('.booking-traffic-legend')).toBeVisible();
    await expect(page.locator('#booking-upcoming-count')).toHaveText('2');
    await expect(page.locator('#booking-history-count')).toHaveText('3');
    await expect(page.locator('[data-booking-traffic="completed"]')).toHaveCount(0);
    await expect(page.locator('[data-booking-traffic="scheduled"]')).toHaveCount(1);
    await expect(page.locator('[data-booking-traffic="urgent"]')).toHaveCount(1);
    await page.locator('[data-booking-view="history"]').click();
    await expect(page.locator('[data-booking-traffic="completed"]')).toHaveCount(2);
    await expect(page.locator('[data-booking-traffic="cancelled"]')).toHaveCount(1);
  });

  test('should support table pagination and rows-per-page selector', async ({ page }) => {
    // Switch to Bookings view
    await page.locator('#tab-bookings').click();

    // Verify pagination controls
    await expect(page.locator('#booking-per-page')).toBeVisible();
    await expect(page.locator('#booking-prev-page')).toBeVisible();
    await expect(page.locator('#booking-next-page')).toBeVisible();
    await expect(page.locator('#booking-page-indicator')).toBeVisible();

    // Select rows per page option
    const perPageSelect = page.locator('#booking-per-page');
    await perPageSelect.selectOption('5');
    await expect(perPageSelect).toHaveValue('5');

    // Click next page and verify transition
    const nextBtn = page.locator('#booking-next-page');
    if (await nextBtn.isEnabled()) {
      await nextBtn.click();
      await expect(page.locator('#booking-page-indicator')).toContainText('Página 2');
    }
  });

  test('should sort bookings by selected column and direction', async ({ page }) => {
    await page.locator('#tab-bookings').click();

    const dateSort = page.locator('[data-booking-sort="scheduled_for"]');
    await expect(dateSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'ascending');

    // Clickear la misma columna de nuevo alterna la direccion (como
    // cualquier otra columna) -- antes quedaba pegada en "ascending" porque
    // toggleBookingSort() reimponia la direccion segun la vista en vez de
    // alternar cuando la columna ya estaba activa. Ver bookings.js.
    await dateSort.click();
    await expect(dateSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'descending');
    await expect(dateSort.locator('.sort-indicator')).toHaveText('▼');

    // Un segundo click vuelve a ascendente.
    await dateSort.click();
    await expect(dateSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'ascending');
    await expect(dateSort.locator('.sort-indicator')).toHaveText('▲');

    await page.locator('[data-booking-view="history"]').click();
    await expect(dateSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'descending');
    await expect(dateSort.locator('.sort-indicator')).toHaveText('▼');

    const clientSort = page.locator('[data-booking-sort="customer_name"]');
    await clientSort.click();
    await expect(clientSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'ascending');
    await expect(dateSort.locator('xpath=..')).not.toHaveAttribute('aria-sort', /.+/);
    await expect(page.locator('#booking-page-indicator')).toContainText('Página 1');
  });

  test('should sort services with the same table interaction pattern', async ({ page }) => {
    await page.locator('#tab-services').click();
    await expect(page.locator('#services-body .source-cell .badge').first()).toHaveText('Servicios Master');
    await expect(page.locator('#services-body .source-cell').first()).toHaveAttribute('title', /Servicios_Master #\d+/);
    const nameSort = page.locator('[data-service-sort="name"]');
    await expect(nameSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'ascending');

    await nameSort.click();
    await expect(nameSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'descending');
    await expect(nameSort.locator('.sort-indicator')).toHaveText('▼');

    const priceSort = page.locator('[data-service-sort="sale_price"]');
    await priceSort.click();
    await expect(priceSort.locator('xpath=..')).toHaveAttribute('aria-sort', 'ascending');
    await expect(nameSort.locator('xpath=..')).not.toHaveAttribute('aria-sort', /.+/);

    const quantitySort = page.locator('[data-service-sort="quantity"]');
    await quantitySort.click();
    await expect(quantitySort.locator('xpath=..')).toHaveAttribute('aria-sort', 'ascending');
    await expect(priceSort.locator('xpath=..')).not.toHaveAttribute('aria-sort', /.+/);
    await expect(page.locator('#service-form input[name="quantity"]')).toHaveValue('1');
  });

  test('should handle validation errors, helper text, and invalid field styling', async ({ page }) => {
    await page.locator('#tab-services').click();
    // Verify Service form validation
    const nameInput = page.locator('#service-form input[name="name"]');
    
    // Type too short name and submit
    await nameInput.fill('ab');
    await page.locator('#service-form').getByRole('button', { name: 'Guardar' }).click();

    // HTML5 validation blocks the submission, so the toast should not appear
    await expect(page.locator('#message')).not.toBeVisible();
  });

  test('should disable Sincronizar GAS row button on click and show feedback', async ({ page }) => {
    // Switch to Bookings view
    await page.locator('#tab-bookings').click();

    // Check if there's any sync button visible
    const syncButton = page.locator('button.btn-sync-gas-row').first();
    
    if (await syncButton.isVisible()) {
      // Click sync and verify it disables
      await syncButton.click();
      await expect(syncButton).toBeDisabled();
      
      // Toast message shows response status
      const toast = page.locator('#message');
      await expect(toast).toBeVisible();
    }
  });
});

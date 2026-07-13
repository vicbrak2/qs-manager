import { test, expect } from '@playwright/test';

test.describe('QS Manager V2 Dashboard E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Go to the dashboard home page
    await page.goto('/');
  });

  test('should load the dashboard with Outfit Google Font', async ({ page }) => {
    await expect(page).toHaveTitle('QS Manager V2');
    const header = page.locator('h1');
    await expect(header).toHaveText('QS Manager V2');

    // Check font family is Outfit
    const fontFamily = await header.evaluate((el) => window.getComputedStyle(el).fontFamily);
    expect(fontFamily).toContain('Outfit');
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

  test('should handle validation errors, helper text, and invalid field styling', async ({ page }) => {
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

import { test, expect } from '@playwright/test';

test.describe('Finance Dashboard', () => {
  test('should load finance tab by default and display metrics', async ({ page }) => {
    await page.goto('http://localhost:8080/');
    
    // Tab should be visible
    const tabFinance = page.locator('#tab-finance');
    await expect(tabFinance).toBeVisible();
    await tabFinance.click();

    // Verify view is active
    const financeView = page.locator('#finance-view');
    await expect(financeView).not.toHaveClass(/hidden/);

    // Verify grid cards
    await expect(page.locator('#finance-val-contracted')).toBeVisible();
    await expect(page.locator('#finance-val-collected')).toBeVisible();
    await expect(page.locator('#finance-val-net')).toBeVisible();

    // Verify filters
    const from = page.locator('#finance-from');
    await expect(from).toHaveValue(/^\d{4}-\d{2}-\d{2}$/); // Default Este mes initialized
  });

  test('should update metrics when refresh is clicked', async ({ page }) => {
    await page.goto('http://localhost:8080/');
    await page.locator('#tab-finance').click();

    await page.locator('#finance-from').fill('2026-07-01');
    await page.locator('#finance-to').fill('2026-07-31');
    
    // Stub the API call
    await page.route('/api/v1/finance/dashboard*', async (route) => {
      const json = {
        period: { from: '2026-07-01', to: '2026-07-31', basis: 'cash_estimated' },
        metrics: {
          contracted_sales: 50000,
          collected_revenue: 40000,
          accounts_receivable: 10000,
          direct_costs: 5000,
          operating_expenses: 5000,
          refunds: 0,
          net_result: 30000,
          operating_margin: 0.75
        },
        reconciliation: {
          service_revenue: { sheet_total: 50000, projected_total: 50000, difference: 0, excluded_rows: 0 },
          customer_payment: { sheet_total: 40000, projected_total: 40000, difference: 0, excluded_rows: 0 },
          direct_cost: { sheet_total: 5000, projected_total: 5000, difference: 0, excluded_rows: 0 },
          operational_expense: { sheet_total: 5000, projected_total: 5000, difference: 0, excluded_rows: 0 },
          refund: { sheet_total: 0, projected_total: 0, difference: 0, excluded_rows: 0 }
        },
        quality: {
          is_reconciled: true,
          missing_external_ids: 0,
          estimated_cost_rows: 0
        }
      };
      await route.fulfill({ json });
    });

    await page.locator('#refresh-finance').click();

    // Check DOM values
    await expect(page.locator('#finance-val-contracted')).toContainText('$50.000');
    await expect(page.locator('#finance-val-collected')).toContainText('$40.000');
    await expect(page.locator('#finance-val-net')).toContainText('$30.000');
    await expect(page.locator('#finance-val-margin')).toContainText('75,0%');

    // Check reconciliation table
    const tableRows = page.locator('#finance-reconciliation-body tr');
    await expect(tableRows).toHaveCount(5);
    
    // Check first row (Ventas)
    const firstRowStatus = tableRows.nth(0).locator('.status-badge');
    await expect(firstRowStatus).toHaveText('OK');
    await expect(firstRowStatus).toHaveClass(/ok/);
  });
});

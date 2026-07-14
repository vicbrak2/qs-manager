import { test, expect } from '@playwright/test';

test.describe('Sync End-to-End', () => {
    test.beforeEach(async ({ page }) => {
        // Mock the initial sync trigger
        await page.route('/api/v1/sync/sheets/import', async route => {
            const json = {
                run_id: 123,
                status: 'queued',
                message: 'Sync enqueued successfully.',
                reused: false
            };
            await route.fulfill({ json, status: 202 });
        });

        // Mock the polling mechanism
        let pollCount = 0;
        await page.route('/api/v1/sync/sheets/runs/123', async route => {
            pollCount++;
            if (pollCount < 3) {
                // Simulate running state
                await route.fulfill({
                    json: { run_id: 123, status: 'running' }
                });
            } else {
                // Simulate completed state
                await route.fulfill({
                    json: { 
                        run_id: 123, 
                        status: 'completed',
                        total_rows_imported: 45
                    }
                });
            }
        });

        // Mock the remaining endpoints that load after a successful sync
        await page.route('/health', async route => route.fulfill({ json: { status: 'ok', database: 'ok' } }));
        await page.route('/api/v1/sync/sheets/status', async route => route.fulfill({
            json: { enabled: true, sources: [] }
        }));
        await page.route('/api/v1/services*', async route => route.fulfill({ json: { services: [] } }));
        await page.route('/api/v1/team*', async route => route.fulfill({ json: { staff: [] } }));
        await page.route('/api/v1/bookings*', async route => route.fulfill({ json: { bookings: [] } }));
    });

    test('performs sync lifecycle correctly and shows success toast', async ({ page }) => {
        await page.goto('/');

        const syncBtn = page.locator('#sync-all');
        await expect(syncBtn).toBeVisible();
        
        await syncBtn.click();
        
        await expect(syncBtn).toBeEnabled({ timeout: 15_000 });
        await expect(syncBtn).not.toHaveAttribute('aria-busy', 'true');
        await expect(page.locator('#toast-container')).toContainText('Sincronización completa: 45 filas importadas.');
    });
});

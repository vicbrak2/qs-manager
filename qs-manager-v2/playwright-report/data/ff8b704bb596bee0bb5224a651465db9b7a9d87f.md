# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: sync.spec.ts >> Sync End-to-End >> performs sync lifecycle correctly and shows success toast
- Location: tests\e2e\sync.spec.ts:45:9

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText(/45 filas importadas/i)
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 10000ms
  - waiting for getByText(/45 filas importadas/i)

```

```yaml
- banner:
  - heading "QS Manager V2" [level=1]
  - button "Sincronizar todo"
  - text: API ok · DB ok
- main:
  - text: Cannot read properties of undefined (reading 'filter') Servicios
  - strong: "0"
  - text: Reservas
  - strong: "0"
  - text: Confirmadas
  - strong: "0"
  - text: Sync Sheets
  - strong: "--"
  - button "Servicios"
  - button "Reservas"
  - heading "Servicios" [level=2]
  - button "Recargar"
  - button "Nuevo"
  - text: Buscar
  - textbox "Buscar":
    - /placeholder: Nombre, categoria u origen
  - text: Estado
  - combobox "Estado":
    - option "Todos" [selected]
    - option "Activos"
    - option "Inactivos"
  - text: Origen
  - combobox "Origen":
    - option "Todos" [selected]
    - option "Local"
    - option "Sheets"
  - table:
    - rowgroup:
      - row "ID Nombre Categoria Precio Costo Utilidad Margen Origen Activo Acciones":
        - columnheader "ID"
        - columnheader "Nombre"
        - columnheader "Categoria"
        - columnheader "Precio"
        - columnheader "Costo"
        - columnheader "Utilidad"
        - columnheader "Margen"
        - columnheader "Origen"
        - columnheader "Activo"
        - columnheader "Acciones"
    - rowgroup
  - text: Sin servicios para mostrar.
  - complementary:
    - heading "Nuevo servicio" [level=2]
    - button "Limpiar"
    - text: Nombre
    - textbox "Nombre *"
    - text: "* Categoria"
    - textbox "Categoria"
    - text: Duracion
    - spinbutton "Duracion"
    - text: Precio venta
    - spinbutton "Precio venta"
    - text: Costo total
    - spinbutton "Costo total"
    - text: Utilidad
    - spinbutton "Utilidad"
    - text: Margen
    - spinbutton "Margen"
    - text: Estado margen
    - textbox "Estado margen"
    - text: Activo
    - combobox "Activo":
      - option "Si" [selected]
      - option "No"
    - button "Guardar"
    - button "Borrar" [disabled]
- text: Cannot read properties of undefined (reading 'filter')
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | test.describe('Sync End-to-End', () => {
  4  |     test.beforeEach(async ({ page }) => {
  5  |         // Mock the initial sync trigger
  6  |         await page.route('/api/v1/sync/sheets/import', async route => {
  7  |             const json = {
  8  |                 run_id: 123,
  9  |                 status: 'queued',
  10 |                 message: 'Sync enqueued successfully.',
  11 |                 reused: false
  12 |             };
  13 |             await route.fulfill({ json, status: 202 });
  14 |         });
  15 | 
  16 |         // Mock the polling mechanism
  17 |         let pollCount = 0;
  18 |         await page.route('/api/v1/sync/sheets/runs/123', async route => {
  19 |             pollCount++;
  20 |             if (pollCount < 3) {
  21 |                 // Simulate running state
  22 |                 await route.fulfill({
  23 |                     json: { run_id: 123, status: 'running' }
  24 |                 });
  25 |             } else {
  26 |                 // Simulate completed state
  27 |                 await route.fulfill({
  28 |                     json: { 
  29 |                         run_id: 123, 
  30 |                         status: 'completed',
  31 |                         total_rows_imported: 45
  32 |                     }
  33 |                 });
  34 |             }
  35 |         });
  36 | 
  37 |         // Mock the remaining endpoints that load after a successful sync
  38 |         await page.route('/api/v1/health', async route => route.fulfill({ json: { status: 'ok', worker_alive: true } }));
  39 |         await page.route('/api/v1/sync/sheets/status', async route => route.fulfill({ json: { last_sync: '2023-01-01T00:00:00Z', is_syncing: false } }));
  40 |         await page.route('/api/v1/services*', async route => route.fulfill({ json: { data: [] } }));
  41 |         await page.route('/api/v1/staff*', async route => route.fulfill({ json: { data: [] } }));
  42 |         await page.route('/api/v1/bookings*', async route => route.fulfill({ json: { data: [], pagination: { total: 0 } } }));
  43 |     });
  44 | 
  45 |     test('performs sync lifecycle correctly and shows success toast', async ({ page }) => {
  46 |         await page.goto('/');
  47 | 
  48 |         const syncBtn = page.getByRole('button', { name: /sincronizar/i });
  49 |         await expect(syncBtn).toBeVisible();
  50 |         
  51 |         await syncBtn.click();
  52 |         
  53 |         // Ensure the button shows loading state (Skipped in E2E since transition is fast and uses text not aria-busy)
  54 |         
  55 |         // Wait for polling to finish and toast to appear
  56 |         const toastMessage = page.getByText(/45 filas importadas/i);
> 57 |         await expect(toastMessage).toBeVisible({ timeout: 10000 });
     |                                    ^ Error: expect(locator).toBeVisible() failed
  58 |         
  59 |         // Button should be re-enabled
  60 |         await expect(syncBtn).not.toBeDisabled();
  61 |         await expect(syncBtn).not.toHaveAttribute('aria-busy', 'true');
  62 |     });
  63 | });
  64 | 
```
<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

final class WebController
{
    public function register(App $app): void
    {
        $app->get('/', [$this, 'show']);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->html());

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private function html(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QS Manager V2</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap">
  <link rel="stylesheet" href="/assets/css/tokens.css?v=5">
  <link rel="stylesheet" href="/assets/css/main.css?v=5">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <header>
    <div class="topbar">
      <h1>QS Manager V2</h1>
      <div class="topbar-actions">
        <button type="button" id="sync-all" title="Importar todas las planillas y recargar los datos locales">
          <span class="sync-all-icon" aria-hidden="true">↻</span>
          <span id="sync-all-label">Sincronizar todo</span>
        </button>
        <div class="health"><span id="health-dot" class="dot"></span><span id="health">Conectando...</span></div>
      </div>
    </div>
  </header>

  <main>
    <div id="message" class="message"></div>

    <div class="summary">
      <div class="metric"><span>Servicios</span><strong id="metric-services">0</strong></div>
      <div class="metric"><span>Reservas</span><strong id="metric-bookings">0</strong></div>
      <div class="metric"><span>Confirmadas</span><strong id="metric-confirmed">0</strong></div>
      <div class="metric"><span>Sync Sheets</span><strong id="metric-sync">--</strong></div>
    </div>

    <div class="tabs">
      <button id="tab-finance" class="tab active" type="button">Finanzas</button>
      <button id="tab-services" class="tab" type="button">Servicios</button>
      <button id="tab-bookings" class="tab" type="button">Reservas</button>
    </div>

    <section id="finance-view">
      <div class="finance-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div class="finance-filters" style="display: flex; gap: 12px; align-items: center;">
          <label>Desde: <input type="date" id="finance-from"></label>
          <label>Hasta: <input type="date" id="finance-to"></label>
          <label>Base:
            <select id="finance-basis" disabled title="Por ahora solo se soporta modo caja estimado">
              <option value="cash_estimated" selected>Caja (Estimado)</option>
            </select>
          </label>
          <button type="button" id="refresh-finance" class="secondary">Actualizar</button>
        </div>
        <div class="finance-quality" id="finance-quality-indicator" style="font-size: 0.875rem; color: var(--color-gray-600);">
          Cargando métricas...
        </div>
      </div>

      <div class="finance-dashboard-row">
        <div class="finance-grid">
          <div class="finance-card">
            <div class="finance-card-title">Vendido (Contratado)</div>
            <div class="finance-val" id="finance-val-contracted">$ 0</div>
          </div>
          <div class="finance-card">
            <div class="finance-card-title">Cobrado (Ingresos)</div>
            <div class="finance-val" id="finance-val-collected">$ 0</div>
          </div>
          <div class="finance-card">
            <div class="finance-card-title">Por Cobrar</div>
            <div class="finance-val" id="finance-val-receivable">$ 0</div>
          </div>
          <div class="finance-card">
            <div class="finance-card-title">Costos Directos</div>
            <div class="finance-val" id="finance-val-costs">$ 0</div>
          </div>
          <div class="finance-card">
            <div class="finance-card-title">Gastos Operativos</div>
            <div class="finance-val" id="finance-val-expenses">$ 0</div>
          </div>
          <div class="finance-card">
            <div class="finance-card-title">Devoluciones</div>
            <div class="finance-val" id="finance-val-refunds">$ 0</div>
          </div>
          <div class="finance-card highlight">
            <div class="finance-card-title">Utilidad Neta</div>
            <div class="finance-val" id="finance-val-net">$ 0</div>
          </div>
          <div class="finance-card highlight">
            <div class="finance-card-title">Margen Operativo</div>
            <div class="finance-val" id="finance-val-margin">0%</div>
          </div>
        </div>

        <div class="panel chart-panel">
          <div class="panel-head">
            <h2>Análisis del Período</h2>
          </div>
          <div class="chart-container">
            <canvas id="finance-chart"></canvas>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top: 2rem;">
        <div class="panel-head">
          <h2>Reconciliación vs Fuentes Crudas</h2>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Categoría</th>
                <th style="text-align: right;">Total Sheets</th>
                <th style="text-align: right;">Total BD</th>
                <th style="text-align: right;">Diferencia</th>
                <th style="text-align: right;">Filas Excluidas</th>
                <th style="text-align: center;">Estado</th>
              </tr>
            </thead>
            <tbody id="finance-reconciliation-body">
              <tr><td colspan="6" style="text-align: center;">Cargando reconciliación...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="reconciliation-notes" style="margin-top: 1rem; font-size: 0.875rem; color: var(--color-gray-600); line-height: 1.4;">
          <p>💡 <strong>Nota sobre Descuadre:</strong> Un descuadre de $-45.000 (Ventas de Servicios y Pagos de Clientes) es normal si existen talleres registrados en el periodo. Los ingresos por talleres se integran en la base de datos pero provienen de la pestaña externa "Talleres", la cual no está incluida en los totales crudos de la Bitácora/Flujo de Caja principal.</p>
        </div>
      </div>
    </section>

    <section id="services-view" class="workspace hidden">
      <div class="panel">
        <div class="panel-head">
          <h2>Servicios</h2>
          <div class="actions">
            <button class="secondary" type="button" id="refresh-services">Recargar</button>
            <button type="button" id="new-service">Nuevo</button>
          </div>
        </div>
        <div class="filters">
          <label>Buscar
            <input id="service-filter-text" placeholder="Nombre, categoria u origen">
          </label>
          <label>Estado
            <select id="service-filter-active">
              <option value="">Todos</option>
              <option value="true">Activos</option>
              <option value="false">Inactivos</option>
            </select>
          </label>
          <label>Origen
            <select id="service-filter-source">
              <option value="">Todos</option>
              <option value="local">Local</option>
              <option value="sheet">Sheets</option>
            </select>
          </label>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th aria-sort="ascending"><button type="button" class="sort-btn active" data-service-sort="name">Nombre <span class="sort-indicator">▲</span></button></th>
                <th>Categoria</th>
                <th><button type="button" class="sort-btn" data-service-sort="quantity">Cantidad <span class="sort-indicator"></span></button></th>
                <th><button type="button" class="sort-btn" data-service-sort="sale_price">Precio <span class="sort-indicator"></span></button></th>
                <th>Costo</th>
                <th>Utilidad</th>
                <th>Margen</th>
                <th>Origen</th>
                <th>Activo</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="services-body"></tbody>
          </table>
          <div id="services-empty" class="empty">Sin servicios para mostrar.</div>
        </div>
      </div>

      <aside class="panel side">
        <div class="panel-head">
          <h2 id="service-form-title">Nuevo servicio</h2>
          <button class="secondary" type="button" id="reset-service">Limpiar</button>
        </div>
        <form id="service-form">
          <input type="hidden" name="id">
          <label class="full">Nombre
            <input name="name" required maxlength="160">
          </label>
          <label>Categoria
            <input name="category" maxlength="80">
          </label>
          <label>Cantidad
            <input name="quantity" type="number" min="1" step="1" value="1" required>
          </label>
          <label>Duracion
            <input name="duration_minutes" type="number" min="1" max="1440">
          </label>
          <label>Precio venta
            <input name="sale_price" type="number" min="0" step="1">
          </label>
          <label>Costo total
            <input name="total_cost" type="number" min="0" step="1">
          </label>
          <label>Utilidad
            <input name="utility" type="number" min="0" step="1">
          </label>
          <label>Margen
            <input name="margin_percent" type="number" step="0.01">
          </label>
          <label>Estado margen
            <input name="margin_status" maxlength="40">
          </label>
          <label>Activo
            <select name="active">
              <option value="true">Si</option>
              <option value="false">No</option>
            </select>
          </label>
          <div class="actions full">
            <button type="submit" id="save-service">Guardar</button>
            <button class="danger" type="button" id="delete-service" disabled>Borrar</button>
          </div>
        </form>
      </aside>
    </section>

    <section id="bookings-view" class="workspace hidden">
      <div class="panel">
        <div class="panel-head">
          <h2>Reservas</h2>
          <div class="actions">
            <button class="secondary" type="button" id="refresh-bookings">Recargar</button>
            <button type="button" id="new-booking">Nueva</button>
          </div>
        </div>
        <div class="filters">
          <label>Buscar
            <input id="booking-filter-text" placeholder="Cliente, comuna, telefono">
          </label>
          <label>Servicio
            <select id="booking-filter-service">
              <option value="">Todos</option>
            </select>
          </label>
          <label>Staff Member
            <select id="booking-filter-staff">
              <option value="">Todos</option>
            </select>
          </label>
          <label>Estado
            <select id="booking-filter-status">
              <option value="">Todos</option>
              <option value="draft">Draft</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
              <option value="completed">Completed</option>
            </select>
          </label>
          <label>Pago
            <input id="booking-filter-payment" placeholder="Parcial, pendiente">
          </label>
        </div>
        <div class="table-wrap">
          <table class="booking-table">
            <colgroup>
              <col><col><col><col><col><col><col><col><col><col><col><col><col><col><col>
            </colgroup>
            <thead>
              <tr>
                <th>ID</th>
                <th aria-sort="descending"><button type="button" class="sort-btn active" data-booking-sort="scheduled_for">Fecha <span class="sort-indicator">▼</span></button></th>
                <th>Hora</th>
                <th><button type="button" class="sort-btn" data-booking-sort="customer_name">Cliente <span class="sort-indicator"></span></button></th>
                <th>Telefono</th>
                <th>Servicio</th>
                <th>Comuna</th>
                <th>Direccion</th>
                <th>Total</th>
                <th>Saldo</th>
                <th>Pago</th>
                <th>Estado</th>
                <th>Origen</th>
                <th>Sync</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="bookings-body"></tbody>
          </table>
          <div id="bookings-empty" class="empty">Sin reservas para mostrar.</div>
        </div>
        <div class="pagination-controls">
          <div>
            <label style="display: inline-flex; align-items: center; gap: 8px;">
              Filas por página:
              <select id="booking-per-page" style="width: auto; min-height: 30px; padding: 4px 8px;">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="20">20</option>
              </select>
            </label>
          </div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <button id="booking-prev-page" class="secondary" type="button">Anterior</button>
            <span id="booking-page-indicator">Página 1 de 1</span>
            <button id="booking-next-page" class="secondary" type="button">Siguiente</button>
          </div>
        </div>
      </div>

      <aside class="panel side">
        <div class="panel-head">
          <h2 id="booking-form-title">Nueva reserva</h2>
          <button class="secondary" type="button" id="reset-booking">Limpiar</button>
        </div>
        <form id="booking-form">
          <input type="hidden" name="id">
          <label>Servicio
            <select name="service_id" id="booking-service-select"></select>
          </label>
          <label>Staff
            <select name="staff_id" id="booking-staff-select"></select>
          </label>
          <label>Cliente
            <input name="customer_name" maxlength="160">
          </label>
          <label>Telefono
            <input name="customer_phone" maxlength="40">
          </label>
          <label>Fecha y hora
            <input name="scheduled_for" type="datetime-local">
          </label>
          <label>Estado
            <select name="status" required>
              <option value="draft">draft</option>
              <option value="confirmed">confirmed</option>
              <option value="cancelled">cancelled</option>
              <option value="completed">completed</option>
            </select>
          </label>
          <label class="full">Direccion
            <input name="address" maxlength="240">
          </label>
          <label>Comuna
            <input name="comuna" maxlength="120">
          </label>
          <label>Valor servicio
            <input name="service_value" type="number" min="0" step="1">
          </label>
          <label>Traslado
            <input name="transfer_value" type="number" min="0" step="1">
          </label>
          <label>Abono
            <input name="deposit_amount" type="number" min="0" step="1">
          </label>
          <label>Total
            <input name="total_service" type="number" min="0" step="1">
          </label>
          <label>Saldo
            <input name="balance_due" type="number" min="0" step="1">
          </label>
          <label>Estado pago
            <input name="payment_status" maxlength="40">
          </label>
          <label>Estado servicio
            <input name="service_status" maxlength="40">
          </label>
          <label>ID contrato
            <input name="contract_id" maxlength="80">
          </label>
          <label>Hito
            <input name="milestone" maxlength="80">
          </label>
          <label>Grupo caja
            <input name="cash_group" maxlength="80">
          </label>
          <div class="actions full">
            <button type="submit" id="save-booking">Guardar</button>
            <button class="danger" type="button" id="delete-booking" disabled>Borrar</button>
            <button class="secondary" type="button" id="sync-booking" disabled>Sync GAS</button>
          </div>
        </form>
      </aside>
    </section>
    <dialog id="sync-modal" class="modal">
      <div class="panel modal-panel">
        <div class="panel-head">
          <h2>Resumen de Sincronización</h2>
          <button type="button" id="close-sync-modal" class="modal-close">&times;</button>
        </div>
        <div id="sync-modal-body" class="modal-body">
          <p>Cargando información...</p>
        </div>
        <div class="modal-footer">
          🔒 <strong>Garantía:</strong> Modo de solo lectura. No modifica Sheets.
        </div>
      </div>
    </dialog>
  </main>
  <script type="module" src="/assets/js/app.js"></script>
  <div id="toast-container" class="toast-container"></div>
</body>
</html>
HTML;
    }
}

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
      <button id="tab-bitacora" class="tab" type="button">Bitácora</button>
    </div>

    <section id="finance-view">
      <header class="finance-header">
        <div>
          <p class="finance-eyebrow">Resumen financiero</p>
          <h2>¿Cómo va el estudio?</h2>
          <p class="finance-header-copy">Compara lo vendido con el dinero recibido y los gastos del período.</p>
        </div>
        <div class="finance-filters">
          <label>Desde <input type="date" id="finance-from"></label>
          <label>Hasta <input type="date" id="finance-to"></label>
          <label>Vista
            <select id="finance-basis" disabled title="Por ahora solo se soporta dinero recibido estimado">
              <option value="cash_estimated" selected>Dinero recibido</option>
            </select>
          </label>
          <button type="button" id="refresh-finance" class="secondary">Actualizar</button>
        </div>
      </header>

      <div class="finance-story" aria-live="polite">
        <div class="finance-story-icon" aria-hidden="true">$</div>
        <div>
          <strong id="finance-story-title">Cargando resumen del período...</strong>
          <p id="finance-story-copy">Estamos reuniendo ventas, cobros y egresos.</p>
        </div>
      </div>

      <div class="finance-dashboard-row">
        <div class="finance-main">
          <section class="finance-section" aria-labelledby="finance-flow-title">
            <div class="finance-section-head">
              <div><p class="finance-step">1. Flujo comercial</p><h3 id="finance-flow-title">De lo vendido a lo cobrado</h3></div>
              <span class="finance-period-note">Las ventas pueden cobrarse en fechas diferentes.</span>
            </div>
            <div class="finance-flow-grid">
              <div class="finance-card finance-card--sales"><div class="finance-card-title">Vendido</div><div class="finance-val" id="finance-val-contracted">$ 0</div><p>Valor total de los servicios registrados.</p></div>
              <div class="finance-card finance-card--collected"><div class="finance-card-title">Dinero recibido</div><div class="finance-val" id="finance-val-collected">$ 0</div><p>Pagos y abonos que ya ingresaron.</p></div>
              <div class="finance-card finance-card--committed"><div class="finance-card-title">Abonos comprometidos</div><div class="finance-val" id="finance-val-committed">$ 0</div><p>Abonos de servicios que todavía no han finalizado.</p></div>
              <div class="finance-card finance-card--pending"><div class="finance-card-title">Pendiente de cobro</div><div class="finance-val" id="finance-val-receivable">$ 0</div><p>Parte vendida que todavía no aparece pagada.</p></div>
            </div>
          </section>

          <section class="finance-section" aria-labelledby="finance-result-title">
            <div class="finance-section-head"><div><p class="finance-step">2. Resultado de caja</p><h3 id="finance-result-title">Lo que quedó después de egresos</h3></div></div>
            <div class="finance-result-grid">
              <div class="finance-card finance-card--realized"><div class="finance-card-title">Ingresos realizados</div><div class="finance-val" id="finance-val-realized">$ 0</div><p>Valor de servicios terminados en el período.</p></div>
              <div class="finance-card finance-card--expense"><div class="finance-card-title">Pago a profesionales</div><div class="finance-val" id="finance-val-costs">$ 0</div><p>Costos asociados directamente a los servicios.</p></div>
              <div class="finance-card finance-card--expense"><div class="finance-card-title">Gastos fijos</div><div class="finance-val" id="finance-val-fixed-expenses">$ 0</div><p>Arriendo y suscripciones mensuales confirmadas.</p><small id="finance-fixed-expense-status">Sin partidas pendientes</small></div>
              <div class="finance-card finance-card--expense"><div class="finance-card-title">Otros gastos</div><div class="finance-val" id="finance-val-expenses">$ 0</div><p>Gastos variables pagados durante el período.</p></div>
              <div class="finance-card finance-card--expense"><div class="finance-card-title">Devoluciones</div><div class="finance-val" id="finance-val-refunds">$ 0</div><p>Dinero devuelto a clientes.</p></div>
              <div class="finance-card finance-card--result"><div class="finance-card-title">Resultado disponible</div><div class="finance-val" id="finance-val-net">$ 0</div><p>Recibido menos costos, gastos y devoluciones.</p><button type="button" class="finance-detail-button" id="finance-details-toggle" aria-expanded="false" aria-controls="finance-available-details">Ver detalle por servicio</button></div>
              <div class="finance-card finance-card--margin"><div class="finance-card-title">Margen sobre lo cobrado</div><div class="finance-val" id="finance-val-margin">0%</div><p>Porcentaje del cobro que quedó disponible.</p></div>
            </div>
          </section>
        </div>

        <div class="panel chart-panel">
          <div class="panel-head"><div><p class="finance-step">Vista rápida</p><h2>Cómo se mueve el dinero</h2></div></div>
          <div class="chart-container"><canvas id="finance-chart"></canvas></div>
          <p class="chart-help">La diferencia entre “Vendido” y “Recibido” corresponde a pagos pendientes.</p>
        </div>
      </div>

      <section class="panel finance-details hidden" id="finance-available-details" aria-labelledby="finance-details-title">
        <div class="panel-head finance-details-head">
          <div><p class="finance-step">Detalle del disponible</p><h2 id="finance-details-title">Servicios finalizados que generaron utilidad</h2><p>Los abonos de reservas futuras no se incluyen. La utilidad nace al finalizar el servicio y registrar su costo profesional.</p></div>
          <button type="button" class="secondary" id="finance-details-close">Cerrar</button>
        </div>
        <div class="table-wrap">
          <table class="data-table finance-details-table">
            <thead><tr><th>Fecha servicio</th><th>Cliente</th><th>Servicio finalizado</th><th class="numeric">Ingreso realizado</th><th class="numeric">Costo profesional</th><th class="numeric">Utilidad realizada</th></tr></thead>
            <tbody id="finance-details-body"><tr><td colspan="6">Abre el detalle para consultar los servicios.</td></tr></tbody>
            <tfoot><tr><th colspan="3">Subtotal por servicios finalizados</th><th class="numeric" id="finance-details-realized">$ 0</th><th class="numeric" id="finance-details-costs">$ 0</th><th class="numeric" id="finance-details-service-total">$ 0</th></tr></tfoot>
          </table>
        </div>
        <div class="finance-details-adjustments">
          <h3>Deducciones generales del período</h3>
          <dl><div><dt>Costos sin abono asociado</dt><dd id="finance-details-unmatched">$ 0</dd></div><div><dt>Gastos fijos</dt><dd id="finance-details-fixed-expenses">$ 0</dd></div><div><dt>Otros gastos</dt><dd id="finance-details-expenses">$ 0</dd></div><div><dt>Devoluciones</dt><dd id="finance-details-refunds">$ 0</dd></div><div class="finance-details-final"><dt>Resultado disponible final</dt><dd id="finance-details-net">$ 0</dd></div></dl>
        </div>
      </section>

      <details class="panel finance-audit" id="finance-audit">
        <summary>
          <span><strong>Calidad de los datos</strong><small>Compara las planillas originales con la información usada por la aplicación.</small></span>
          <span class="finance-quality" id="finance-quality-indicator">Comprobando...</span>
        </summary>
        <div class="finance-audit-content">
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Concepto</th><th class="numeric">En planillas</th><th class="numeric">En la aplicación</th><th class="numeric">Diferencia</th><th class="numeric">Omitidas</th><th>Revisión</th></tr></thead>
              <tbody id="finance-reconciliation-body"><tr><td colspan="6">Comprobando información...</td></tr></tbody>
            </table>
          </div>
          <div class="reconciliation-notes"><strong>¿Qué significa?</strong><p>“Cuadra” indica que ambas fuentes tienen el mismo total. “Revisar” muestra una diferencia real que debe investigarse; no modifica automáticamente las planillas.</p></div>
        </div>
      </details>
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
            <input name="sale_price" type="number" min="0" step="1" required>
          </label>
          <label>Costo total
            <input name="total_cost" type="number" min="0" step="1" required>
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
        <div class="booking-traffic-legend" aria-label="Semáforo de reservas">
          <span><i class="traffic-dot completed"></i>Completada o pasada</span>
          <span><i class="traffic-dot scheduled"></i>Faltan más de 7 días</span>
          <span><i class="traffic-dot urgent"></i>Faltan 7 días o menos</span>
          <span><i class="traffic-dot cancelled"></i>Cancelada</span>
        </div>
        <div class="booking-view-switch" role="group" aria-label="Vista de reservas">
          <button type="button" class="active" data-booking-view="upcoming" aria-pressed="true">Próximas <span id="booking-upcoming-count">0</span></button>
          <button type="button" data-booking-view="history" aria-pressed="false">Historial <span id="booking-history-count">0</span></button>
        </div>
        <div class="booking-list-heading">
          <div><h3 id="booking-list-title">Próximas reservas</h3><p id="booking-list-copy">Ordenadas desde la atención pendiente más cercana.</p></div>
        </div>
        <div class="table-wrap">
          <table class="booking-table">
            <colgroup>
              <col><col><col><col><col><col><col><col><col><col><col><col><col><col><col>
            </colgroup>
            <thead>
              <tr>
                <th>ID</th>
                <th aria-sort="ascending"><button type="button" class="sort-btn active" data-booking-sort="scheduled_for">Fecha <span class="sort-indicator">▲</span></button></th>
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
          <div id="booking-availability" class="availability-hint full hidden" aria-live="polite"></div>
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
    <section id="bitacora-view" class="workspace hidden">
      <div class="panel">
        <div class="panel-head">
          <h2>Bitácora</h2>
          <div class="actions">
            <button class="secondary" type="button" id="refresh-bitacoras">Recargar</button>
            <button type="button" id="new-bitacora">Nueva</button>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Clienta</th>
                <th>Equipo</th>
                <th>Traslado</th>
                <th>Precio</th>
                <th>Margen</th>
                <th>Notas</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="bitacoras-body"></tbody>
          </table>
          <div id="bitacoras-empty" class="empty">Sin bitácoras registradas.</div>
        </div>
      </div>

      <aside class="panel side">
        <div class="panel-head">
          <h2 id="bitacora-form-title">Nueva bitácora</h2>
          <div class="actions">
            <div id="bitacora-booking-link" class="bitacora-link-chip hidden"></div>
            <button class="secondary" type="button" id="reset-bitacora">Limpiar</button>
          </div>
        </div>
        <form id="bitacora-form">
          <input type="hidden" name="id">
          <input type="hidden" name="booking_id">
          <label>Fecha servicio
            <input name="fecha_servicio" type="date" required>
          </label>
          <label>Tipo servicio
            <input name="tipo_servicio" maxlength="120" required>
          </label>
          <label class="full">Clienta
            <input name="clienta_nombre" maxlength="160" required>
          </label>
          <label>MUA
            <select name="mua_id" id="bitacora-mua-select"></select>
          </label>
          <label>Estilista
            <select name="estilista_id" id="bitacora-estilista-select"></select>
          </label>
          <label class="full">Dirección servicio
            <input name="direccion_servicio" maxlength="240" required>
          </label>
          <label class="full">Punto de salida
            <input name="punto_salida" maxlength="240" required>
          </label>
          <label class="full">Orden de recogida
            <input name="orden_recogida" placeholder="Ej: MUA primero, luego estilista">
          </label>
          <label>Traslado (min)
            <input name="tiempo_traslado_min" type="number" min="0" step="1" value="0">
          </label>
          <label>Hora llegada
            <input name="hora_llegada" type="time">
          </label>
          <label>Costo staff
            <input name="costo_staff_clp" type="number" min="0" step="1" value="0">
          </label>
          <label>Precio cliente
            <input name="precio_cliente_clp" type="number" min="0" step="1" value="0">
          </label>
          <label class="full">Notas logísticas
            <textarea name="notas_logisticas" rows="2"></textarea>
          </label>
          <div class="actions full">
            <button type="submit" id="save-bitacora">Guardar</button>
          </div>
        </form>
        <div id="bitacora-notes-panel" class="bitacora-notes hidden">
          <h3>Notas de traslado</h3>
          <ul id="bitacora-notes-list"></ul>
          <div class="actions">
            <input id="bitacora-note-message" placeholder="Ej: esperar en la reja principal" maxlength="500">
            <button type="button" class="secondary" id="add-bitacora-note">Agregar nota</button>
          </div>
        </div>
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

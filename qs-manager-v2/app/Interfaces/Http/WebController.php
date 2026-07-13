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
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" rel="stylesheet">
  </noscript>
  <style>
    :root {
      color-scheme: light;
      --bg: #f6f7f9;
      --panel: #ffffff;
      --text: #17202a;
      --muted: #667085;
      --line: #d9dee7;
      --soft: #eef2f6;
      --accent: #0f8b8d;
      --accent-dark: #0a6f70;
      --danger: #c2410c;
      --danger-soft: #fff3ed;
      --ok: #047857;
      --ok-soft: #ecfdf5;
      --warn: #a16207;
      --warn-soft: #fffbeb;
      --radius: 8px;

      /* Typography Scale */
      --font-size-xs: 11px;
      --font-size-sm: 12px;
      --font-size-md: 14px;
      --font-size-lg: 16px;
      --font-size-xl: 20px;
      --font-size-xxl: 24px;
      
      --font-sans: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text);
      font-family: var(--font-sans);
      font-weight: 400;
      font-size: var(--font-size-md);
      letter-spacing: 0;
    }

    header {
      position: sticky;
      top: 0;
      z-index: 20;
      border-bottom: 1px solid var(--line);
      background: rgba(255, 255, 255, 0.96);
    }

    .topbar {
      max-width: 1480px;
      margin: 0 auto;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    #sync-all[aria-busy="true"] .sync-all-icon {
      display: inline-block;
      animation: sync-spin 0.8s linear infinite;
    }

    @keyframes sync-spin {
      to { transform: rotate(360deg); }
    }

    h1 {
      margin: 0;
      font-size: var(--font-size-xl);
      line-height: 1.2;
      font-weight: 700;
    }

    .health {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 32px;
      border: 1px solid var(--line);
      background: var(--soft);
      border-radius: 999px;
      padding: 6px 12px;
      color: var(--muted);
      font-size: 13px;
      white-space: nowrap;
    }

    .dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: var(--warn);
    }

    .dot.ok { background: var(--ok); }

    main {
      max-width: 1480px;
      margin: 0 auto;
      padding: 18px;
    }

    .summary {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 14px;
    }

    .metric {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 12px;
      min-height: 78px;
    }

    .metric span {
      display: block;
      color: var(--muted);
      font-size: var(--font-size-xs);
      margin-bottom: 8px;
    }

    .metric strong {
      display: block;
      font-size: var(--font-size-xxl);
      line-height: 1;
      font-weight: 700;
    }

    .tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 12px;
      overflow-x: auto;
      padding-bottom: 2px;
    }

    .tab {
      min-height: 38px;
      border: 1px solid var(--line);
      background: var(--panel);
      color: var(--text);
      border-radius: var(--radius);
      padding: 8px 12px;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
      white-space: nowrap;
      transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out, transform 0.15s ease-in-out, color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .tab.active {
      background: var(--accent);
      color: white;
      border-color: var(--accent);
    }

    .tab:hover {
      transform: translateY(-1px);
    }

    .tab:active {
      transform: scale(0.95) translateY(0);
    }

    .workspace {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 420px;
      gap: 14px;
      align-items: start;
    }

    .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      overflow: hidden;
    }

    .panel-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 12px;
      border-bottom: 1px solid var(--line);
      background: #fbfcfd;
    }

    h2 {
      margin: 0;
      font-size: var(--font-size-lg);
      font-weight: 700;
    }

    .filters {
      display: grid;
      grid-template-columns: minmax(180px, 2fr) minmax(120px, 1fr) minmax(120px, 1fr);
      gap: 8px;
      padding: 12px;
      border-bottom: 1px solid var(--line);
    }

    label {
      display: grid;
      gap: 6px;
      color: var(--muted);
      font-size: var(--font-size-xs);
      font-weight: 700;
    }

    button, input, select, textarea, .tab {
      font-family: var(--font-sans) !important;
    }

    input, select {
      width: 100%;
      min-height: 38px;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 8px 10px;
      background: white;
      color: var(--text);
      font: inherit;
      min-width: 0;
      transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    input:focus, select:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(15, 139, 141, 0.18);
    }

    input.invalid-field, select.invalid-field {
      border-color: var(--danger) !important;
      outline: none !important;
      box-shadow: 0 0 0 3px rgba(194, 65, 12, 0.18) !important;
    }

    .error-helper-text {
      color: var(--danger);
      font-size: var(--font-size-xs);
      margin-top: 4px;
      display: block;
      font-weight: 400;
    }

    .table-wrap {
      overflow-x: auto;
      max-height: calc(100vh - 285px);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 980px;
    }

    th, td {
      padding: 10px;
      border-bottom: 1px solid var(--line);
      text-align: left;
      vertical-align: middle;
      white-space: nowrap;
    }

    th {
      position: sticky;
      top: 0;
      z-index: 1;
      background: #f8fafc;
      color: var(--muted);
      font-size: var(--font-size-xs);
      text-transform: uppercase;
      font-weight: 700;
    }

    td.wrap {
      white-space: normal;
      min-width: 220px;
      max-width: 320px;
    }

    tr:hover td { background: #fbfcfd; }

    .side {
      position: sticky;
      top: 78px;
    }

    form {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      padding: 12px;
    }

    .full { grid-column: 1 / -1; }

    .actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    button {
      min-height: 36px;
      border: 1px solid transparent;
      border-radius: var(--radius);
      padding: 8px 10px;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
      background: var(--accent);
      color: white;
      transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out, transform 0.15s ease-in-out, color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    button:hover {
      background: var(--accent-dark);
      transform: translateY(-1px);
    }

    button:active {
      transform: scale(0.95) translateY(0);
    }

    button.secondary {
      background: white;
      color: var(--text);
      border-color: var(--line);
    }

    button.secondary:hover {
      background: var(--soft);
      border-color: var(--muted);
      transform: translateY(-1px);
    }

    button.secondary:active {
      transform: scale(0.95) translateY(0);
    }

    button.danger {
      background: var(--danger-soft);
      color: var(--danger);
      border-color: #fed7aa;
    }

    button.danger:hover {
      background: #fed7aa;
      transform: translateY(-1px);
    }

    button.danger:active {
      transform: scale(0.95) translateY(0);
    }

    button:disabled {
      opacity: 0.55;
      cursor: not-allowed;
      transform: none !important;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      min-height: 24px;
      max-width: 180px;
      border-radius: 999px;
      padding: 3px 8px;
      font-size: var(--font-size-xs);
      font-weight: 700;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .badge.ok { background: var(--ok-soft); color: var(--ok); }
    .badge.warn { background: var(--warn-soft); color: var(--warn); }
    .badge.muted { background: var(--soft); color: var(--muted); }
    .badge.danger { background: var(--danger-soft); color: var(--danger); }

    .empty {
      padding: 20px;
      color: var(--muted);
      text-align: center;
    }

    .message {
      display: none;
      margin-bottom: 12px;
      border-radius: var(--radius);
      border: 1px solid var(--line);
      padding: 10px 12px;
      background: white;
      font-weight: 700;
    }
    .message.show { display: block; }
    .message.error { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
    .message.success { border-color: #bbf7d0; background: #f0fdf4; color: #166534; }

    /* Toast Notification System */
    .toast-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-width: 380px;
      width: calc(100% - 40px);
      pointer-events: none;
    }

    .toast {
      pointer-events: auto;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 16px;
      border-radius: var(--radius);
      border: 1px solid var(--line);
      background: var(--panel);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      font-weight: 700;
      font-size: var(--font-size-sm);
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }

    .toast.success {
      border-color: #bbf7d0;
      background: #f0fdf4;
      color: #166534;
    }

    .toast.error {
      border-color: #fecaca;
      background: #fef2f2;
      color: #991b1b;
    }

    .toast.loading {
      border-color: var(--line);
      background: var(--soft);
      color: var(--text);
    }

    .spinner {
      width: 16px;
      height: 16px;
      border: 2px solid rgba(0, 0, 0, 0.1);
      border-left-color: var(--accent);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      display: inline-block;
      flex-shrink: 0;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* GAS Sync Styling */
    .sync-gas-btn {
      background: transparent;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      min-height: 36px;
      width: 36px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      padding: 0;
      transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out, transform 0.15s ease-in-out;
    }
    
    .sync-gas-btn:hover {
      background: var(--soft);
      border-color: var(--muted);
      transform: translateY(-1px);
    }
    
    .sync-gas-btn:active {
      transform: scale(0.95) translateY(0);
    }

    .sync-icon {
      display: inline-block;
      font-size: 16px;
      font-weight: bold;
      transition: transform 0.2s ease, color 0.2s ease;
    }

    .sync-icon.success, .sync-icon.synced {
      color: var(--ok);
    }

    .sync-icon.failed {
      color: var(--danger);
    }

    .sync-icon.skipped {
      color: var(--warn);
    }

    .sync-icon.pending, .sync-icon.not-synced {
      color: var(--muted);
    }

    .sync-icon.spinning {
      animation: spin 1s linear infinite;
      color: var(--accent);
    }

    .hidden { display: none; }

    @media (max-width: 1080px) {
      .workspace { grid-template-columns: 1fr; }
      .side { position: static; }
      .table-wrap { max-height: none; }
    }

    @media (max-width: 760px) {
      main { padding: 12px; }
      .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .filters { grid-template-columns: 1fr; }
      form { grid-template-columns: 1fr; }
      h1 { font-size: 18px; }
      .topbar { align-items: flex-start; }
      .full { grid-column: auto; }
    }
  </style>
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
      <button id="tab-services" class="tab active" type="button">Servicios</button>
      <button id="tab-bookings" class="tab" type="button">Reservas</button>
    </div>

    <section id="services-view" class="workspace">
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
                <th>ID</th><th>Nombre</th><th>Categoria</th><th>Precio</th><th>Costo</th><th>Utilidad</th><th>Margen</th><th>Origen</th><th>Activo</th><th>Acciones</th>
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
        <div class="filters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; padding: 12px; border-bottom: 1px solid var(--line);">
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
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Fecha</th><th>Cliente</th><th>Telefono</th><th>Servicio</th><th>Comuna</th><th>Direccion</th><th>Total</th><th>Saldo</th><th>Pago</th><th>Estado</th><th>Origen</th><th>Acciones</th>
              </tr>
            </thead>
            <tbody id="bookings-body"></tbody>
          </table>
          <div id="bookings-empty" class="empty">Sin reservas para mostrar.</div>
        </div>
        <div class="pagination-controls" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border-top: 1px solid var(--line);">
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
  </main>
  <script>
    const state = {
      services: [],
      staff: [],
      bookings: [],
      activeTab: 'services',
      bookingsPagination: {
        currentPage: 1,
        perPage: 10,
      }
    };

    const $ = (selector) => document.querySelector(selector);
    const money = (value) => value === null || value === undefined || value === '' ? '' : Number(value).toLocaleString('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });
    const text = (value) => value === null || value === undefined ? '' : String(value);
    const dash = (value) => value === null || value === undefined || value === '' ? '—' : value;
    const percent = (value) => value === null || value === undefined || value === '' ? '' : `${(Number(value) * 100).toLocaleString('es-CL', { maximumFractionDigits: 1 })}%`;
    const numberOrNull = (value) => value === '' || value === null || value === undefined ? null : Number(value);
    const idOrNull = (value) => value === '' || value === null || value === undefined ? null : Number(value);

    function escapeHtml(value) {
      return text(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function notify(message, type = 'success', duration = 5000) {
      if (typeof type === 'boolean') {
        type = type ? 'error' : 'success';
      }

      const box = $('#message');
      if (box) {
        box.textContent = message;
        if (type === 'error' || type === true) {
          box.className = 'message error show';
        } else if (type === 'success') {
          box.className = 'message success show';
        } else {
          box.className = 'message show';
        }
        window.clearTimeout(notify.timer);
        if (duration > 0 && type !== 'loading') {
          notify.timer = window.setTimeout(() => box.classList.remove('show'), duration);
        }
      }

      let container = $('#toast-container');
      if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
      }

      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      
      const renderContent = (msg, t) => {
        let content = '';
        if (t === 'loading') {
          content += '<span class="spinner"></span>';
        }
        content += `<span>${escapeHtml(msg)}</span>`;
        return content;
      };

      toast.innerHTML = renderContent(message, type);
      container.appendChild(toast);

      // Trigger animation
      window.setTimeout(() => toast.classList.add('show'), 10);

      let dismissTimer;
      const dismiss = () => {
        toast.classList.remove('show');
        window.setTimeout(() => toast.remove(), 300);
      };

      const startTimer = (d) => {
        if (type !== 'loading' && d > 0) {
          dismissTimer = window.setTimeout(dismiss, d);
        }
      };

      startTimer(duration);

      return {
        dismiss,
        update: (newMsg, newType = 'success', newDuration = 5000) => {
          if (dismissTimer) clearTimeout(dismissTimer);
          if (typeof newType === 'boolean') {
            newType = newType ? 'error' : 'success';
          }
          toast.className = `toast ${newType}`;
          toast.innerHTML = renderContent(newMsg, newType);
          
          if (newType !== 'loading' && newDuration > 0) {
            dismissTimer = window.setTimeout(dismiss, newDuration);
          }

          if (box) {
            box.textContent = newMsg;
            if (newType === 'error' || newType === true) {
              box.className = 'message error show';
            } else if (newType === 'success') {
              box.className = 'message success show';
            } else {
              box.className = 'message show';
            }
            window.clearTimeout(notify.timer);
            if (newDuration > 0 && newType !== 'loading') {
              notify.timer = window.setTimeout(() => box.classList.remove('show'), newDuration);
            }
          }
        }
      };
    }

    async function api(url, options = {}) {
      const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options,
      });
      const body = await response.json();
      if (!response.ok) {
        const details = body.errors ? ' ' + Object.values(body.errors).flat().join(' ') : '';
        const err = new Error((body.error || 'Error de API') + details);
        err.errors = body.errors;
        throw err;
      }
      return body;
    }

    function setTab(tab) {
      state.activeTab = tab;
      $('#tab-services').classList.toggle('active', tab === 'services');
      $('#tab-bookings').classList.toggle('active', tab === 'bookings');
      $('#services-view').classList.toggle('hidden', tab !== 'services');
      $('#bookings-view').classList.toggle('hidden', tab !== 'bookings');
    }

    function formatDate(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
    }

    function toDateTimeLocal(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      const pad = (n) => String(n).padStart(2, '0');
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function fromDateTimeLocal(value) {
      if (!value) return null;
      const d = new Date(value);
      return Number.isNaN(d.getTime()) ? null : d.toISOString();
    }

    function badge(value, kind = 'muted') {
      return `<span class="badge ${kind}">${escapeHtml(value || '')}</span>`;
    }

    function sourceLabel(row) {
      return row.source_sheet ? `${row.source_sheet} #${row.source_row || ''}` : 'local';
    }

    // Fix: declare the missing functions from the original:
    function refreshMetrics() {
      $('#metric-services').textContent = state.services.length;
      $('#metric-bookings').textContent = state.bookings.length;
      $('#metric-confirmed').textContent = state.bookings.filter((booking) => booking.status === 'confirmed').length;
    }

    async function loadHealth() {
      const health = await api('/health');
      $('#health').textContent = `API ${health.status} · DB ${health.database}`;
      $('#health-dot').classList.toggle('ok', health.status === 'ok');
    }

    async function loadSyncStatus() {
      const status = await api('/api/v1/sync/sheets/status');
      const completed = status.sources.filter((source) => source.last_run_status === 'completed').length;
      $('#metric-sync').textContent = status.enabled ? `${completed}/${status.sources.length}` : 'off';
    }

    async function loadServices() {
      const data = await api('/api/v1/services');
      state.services = data.services;
      renderServices();
      fillServiceSelect();
      refreshMetrics();
    }

    async function loadStaff() {
      const data = await api('/api/v1/team');
      state.staff = data.staff;
      fillStaffSelect();
    }

    async function loadBookings() {
      const data = await api('/api/v1/bookings');
      state.bookings = data.bookings;
      renderBookings();
      refreshMetrics();
    }

    async function syncAll() {
      const button = $('#sync-all');
      const label = $('#sync-all-label');
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      label.textContent = 'Sincronizando...';
      const toast = notify('Importando todas las planillas a la base local...', 'loading', 0);

      try {
        const result = await api('/api/v1/sync/sheets/import', { method: 'POST' });
        if (!result.sync) {
          throw new Error(result.error || 'La sincronización de Sheets está desactivada.');
        }

        const sources = Object.values(result.sync.sources || {});
        const failed = sources.filter((source) => source.status !== 'completed');
        const imported = sources.reduce((total, source) => total + Number(source.rows_imported || 0), 0);

        await Promise.all([loadHealth(), loadSyncStatus(), loadServices(), loadStaff(), loadBookings()]);

        if (failed.length > 0) {
          toast.update(`Sincronización parcial: ${imported} filas importadas y ${failed.length} fuente(s) con error.`, 'error', 7000);
        } else {
          toast.update(`Sincronización completa: ${imported} filas importadas.`, 'success', 5000);
        }
      } catch (error) {
        toast.update(error.message, 'error', 7000);
      } finally {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        label.textContent = 'Sincronizar todo';
      }
    }

    function serviceMatches(service) {
      const query = $('#service-filter-text').value.trim().toLowerCase();
      const active = $('#service-filter-active').value;
      const source = $('#service-filter-source').value;
      const haystack = [service.name, service.category, service.margin_status, service.source_sheet].join(' ').toLowerCase();
      if (query && !haystack.includes(query)) return false;
      if (active && String(service.active) !== active) return false;
      if (source === 'local' && service.source_sheet) return false;
      if (source === 'sheet' && !service.source_sheet) return false;
      return true;
    }

    function renderServices() {
      const rows = state.services.filter(serviceMatches);
      $('#services-empty').style.display = rows.length ? 'none' : 'block';
      $('#services-body').innerHTML = rows.map((service) => `
        <tr>
          <td>${service.id}</td>
          <td class="wrap">${escapeHtml(service.name)}</td>
          <td>${escapeHtml(service.category || '')}</td>
          <td>${money(service.sale_price)}</td>
          <td>${money(service.total_cost)}</td>
          <td>${money(service.utility)}</td>
          <td>${percent(service.margin_percent)}</td>
          <td>${badge(sourceLabel(service), service.source_sheet ? 'warn' : 'muted')}</td>
          <td>${badge(service.active ? 'activo' : 'inactivo', service.active ? 'ok' : 'muted')}</td>
          <td><button class="secondary" type="button" data-edit-service="${service.id}">Editar</button></td>
        </tr>
      `).join('');
    }

    function bookingMatches(booking) {
      const query = $('#booking-filter-text').value.trim().toLowerCase();
      const serviceId = $('#booking-filter-service').value;
      const staffId = $('#booking-filter-staff').value;
      const status = $('#booking-filter-status').value;
      const payment = $('#booking-filter-payment').value.trim().toLowerCase();
      
      const haystack = [booking.customer_name, booking.customer_phone, booking.service_name, booking.comuna, booking.address].join(' ').toLowerCase();
      if (query && !haystack.includes(query)) return false;
      if (serviceId && String(booking.service_id) !== serviceId) return false;
      if (staffId && String(booking.staff_id) !== staffId) return false;
      if (status && booking.status !== status) return false;
      if (payment && !text(booking.payment_status).toLowerCase().includes(payment)) return false;
      return true;
    }

    function renderBookings() {
      const filtered = state.bookings.filter(bookingMatches);
      $('#bookings-empty').style.display = filtered.length ? 'none' : 'block';
      
      const perPage = Number($('#booking-per-page').value);
      const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
      
      if (state.bookingsPagination.currentPage > totalPages) {
        state.bookingsPagination.currentPage = totalPages;
      }
      if (state.bookingsPagination.currentPage < 1) {
        state.bookingsPagination.currentPage = 1;
      }
      const currentPage = state.bookingsPagination.currentPage;
      
      const startIndex = (currentPage - 1) * perPage;
      const endIndex = startIndex + perPage;
      const rows = filtered.slice(startIndex, endIndex);

      $('#booking-page-indicator').textContent = `Página ${currentPage} de ${totalPages}`;
      $('#booking-prev-page').disabled = currentPage === 1;
      $('#booking-next-page').disabled = currentPage === totalPages;

      $('#bookings-body').innerHTML = rows.map((booking) => {
        const statusKind = booking.status === 'confirmed' ? 'ok' : booking.status === 'cancelled' ? 'danger' : booking.status === 'completed' ? 'warn' : 'muted';
        
        let syncStatusLabel = 'local';
        let syncStatusKind = 'muted';
        if (booking.gas_last_sync_status) {
          if (booking.gas_last_sync_status === 'synced' || booking.gas_last_sync_status === 'success') {
            syncStatusLabel = 'GAS: synced';
            syncStatusKind = 'ok';
          } else if (booking.gas_last_sync_status === 'failed') {
            syncStatusLabel = 'GAS: failed';
            syncStatusKind = 'danger';
          } else {
            syncStatusLabel = `GAS: ${booking.gas_last_sync_status}`;
            syncStatusKind = 'warn';
          }
        }

        const syncStatus = booking.gas_last_sync_status || 'not-synced';
        let syncStatusClass = 'not-synced';
        if (syncStatus === 'synced' || syncStatus === 'success') {
          syncStatusClass = 'success';
        } else if (syncStatus === 'failed') {
          syncStatusClass = 'failed';
        } else if (syncStatus === 'skipped') {
          syncStatusClass = 'skipped';
        } else if (syncStatus === 'pending' || syncStatus === 'not-synced') {
          syncStatusClass = 'pending';
        }
        
        return `
          <tr>
            <td>${booking.id}</td>
            <td>${formatDate(booking.scheduled_for)}</td>
            <td class="wrap">${escapeHtml(booking.customer_name || '')}</td>
            <td>${escapeHtml(dash(booking.customer_phone))}</td>
            <td class="wrap">${escapeHtml(booking.service_name || '')}</td>
            <td>${escapeHtml(dash(booking.comuna))}</td>
            <td class="wrap">${escapeHtml(dash(booking.address))}</td>
            <td>${money(booking.total_service)}</td>
            <td>${money(booking.balance_due)}</td>
            <td>${badge(booking.payment_status || '', 'muted')}</td>
            <td>${badge(booking.status, statusKind)}</td>
            <td>${badge(sourceLabel(booking), booking.source_sheet ? 'warn' : 'muted')}<br>${badge(syncStatusLabel, syncStatusKind)}</td>
            <td>
              <div style="display: flex; gap: 6px; align-items: center;">
                <button class="secondary" type="button" data-edit-booking="${booking.id}">Editar</button>
                <button class="sync-gas-btn btn-sync-gas-row" type="button" data-sync-booking-id="${booking.id}" title="${escapeHtml(booking.gas_last_sync_message || 'Sincronizar GAS')}">
                  <span class="sync-icon ${syncStatusClass}">↻</span>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function fillServiceSelect() {
      const activeServices = state.services.filter((service) => service.active);
      $('#booking-service-select').innerHTML = '<option value="">Sin servicio</option>' + activeServices
        .map((service) => `<option value="${service.id}">${escapeHtml(service.name)}</option>`)
        .join('');
      $('#booking-filter-service').innerHTML = '<option value="">Todos</option>' + activeServices
        .map((service) => `<option value="${service.id}">${escapeHtml(service.name)}</option>`)
        .join('');
    }

    function fillStaffSelect() {
      const activeStaff = state.staff.filter((person) => person.active);
      $('#booking-staff-select').innerHTML = '<option value="">Sin staff</option>' + activeStaff
        .map((person) => `<option value="${person.id}">${escapeHtml(person.display_name)}</option>`)
        .join('');
      $('#booking-filter-staff').innerHTML = '<option value="">Todos</option>' + activeStaff
        .map((person) => `<option value="${person.id}">${escapeHtml(person.display_name)}</option>`)
        .join('');
    }

    function clearFormErrors(form) {
      form.querySelectorAll('.invalid-field').forEach(el => el.classList.remove('invalid-field'));
      form.querySelectorAll('.error-helper-text').forEach(el => el.remove());
    }

    function showFormErrors(form, errors) {
      clearFormErrors(form);
      if (!errors) return;
      Object.keys(errors).forEach((field) => {
        const input = form.querySelector(`[name="${field}"]`);
        if (input) {
          input.classList.add('invalid-field');
          const helper = document.createElement('span');
          helper.className = 'error-helper-text';
          const msg = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
          helper.textContent = msg || '';
          input.parentNode.appendChild(helper);
        }
      });
    }

    function resetServiceForm() {
      const form = $('#service-form');
      form.reset();
      clearFormErrors(form);
      form.querySelector('[name=id]').value = '';
      $('#service-form-title').textContent = 'Nuevo servicio';
      $('#delete-service').disabled = true;
    }

    function editService(id) {
      const service = state.services.find((item) => item.id === id);
      if (!service) return;
      const form = $('#service-form');
      clearFormErrors(form);
      const fields = form.elements;
      fields.id.value = service.id;
      fields.name.value = service.name || '';
      fields.category.value = service.category || '';
      fields.duration_minutes.value = service.duration_minutes || '';
      fields.sale_price.value = service.sale_price || '';
      fields.total_cost.value = service.total_cost || '';
      fields.utility.value = service.utility || '';
      fields.margin_percent.value = service.margin_percent || '';
      fields.margin_status.value = service.margin_status || '';
      fields.active.value = String(Boolean(service.active));
      $('#service-form-title').textContent = `Servicio #${service.id}`;
      $('#delete-service').disabled = false;
    }

    function servicePayload() {
      const form = $('#service-form');
      const fields = form.elements;
      return {
        name: fields.name.value.trim(),
        category: fields.category.value.trim() || null,
        duration_minutes: numberOrNull(fields.duration_minutes.value),
        active: fields.active.value === 'true',
        sale_price: numberOrNull(fields.sale_price.value),
        total_cost: numberOrNull(fields.total_cost.value),
        utility: numberOrNull(fields.utility.value),
        margin_percent: numberOrNull(fields.margin_percent.value),
        margin_status: fields.margin_status.value.trim() || null,
      };
    }

    function resetBookingForm() {
      const form = $('#booking-form');
      form.reset();
      clearFormErrors(form);
      form.querySelector('[name=id]').value = '';
      $('#booking-form-title').textContent = 'Nueva reserva';
      $('#delete-booking').disabled = true;
      $('#sync-booking').disabled = true;
    }

    function editBooking(id) {
      const booking = state.bookings.find((item) => item.id === id);
      if (!booking) return;
      const form = $('#booking-form');
      clearFormErrors(form);
      const fields = form.elements;
      fields.id.value = booking.id;
      fields.service_id.value = booking.service_id || '';
      fields.staff_id.value = booking.staff_id || '';
      fields.customer_name.value = booking.customer_name || '';
      fields.customer_phone.value = booking.customer_phone || '';
      fields.scheduled_for.value = toDateTimeLocal(booking.scheduled_for);
      fields.status.value = booking.status || 'draft';
      fields.address.value = booking.address || '';
      fields.comuna.value = booking.comuna || '';
      fields.service_value.value = booking.service_value || '';
      fields.transfer_value.value = booking.transfer_value || '';
      fields.deposit_amount.value = booking.deposit_amount || '';
      fields.total_service.value = booking.total_service || '';
      fields.balance_due.value = booking.balance_due || '';
      fields.payment_status.value = booking.payment_status || '';
      fields.service_status.value = booking.service_status || '';
      fields.contract_id.value = booking.contract_id || '';
      fields.milestone.value = booking.milestone || '';
      fields.cash_group.value = booking.cash_group || '';
      $('#booking-form-title').textContent = `Reserva #${booking.id}`;
      $('#delete-booking').disabled = false;
      $('#sync-booking').disabled = false;
    }

    function bookingPayload() {
      const form = $('#booking-form');
      const fields = form.elements;
      return {
        service_id: idOrNull(fields.service_id.value),
        staff_id: idOrNull(fields.staff_id.value),
        customer_name: fields.customer_name.value.trim() || null,
        customer_phone: fields.customer_phone.value.trim() || null,
        scheduled_for: fromDateTimeLocal(fields.scheduled_for.value),
        status: fields.status.value,
        address: fields.address.value.trim() || null,
        comuna: fields.comuna.value.trim() || null,
        service_value: numberOrNull(fields.service_value.value),
        transfer_value: numberOrNull(fields.transfer_value.value),
        deposit_amount: numberOrNull(fields.deposit_amount.value),
        total_service: numberOrNull(fields.total_service.value),
        balance_due: numberOrNull(fields.balance_due.value),
        payment_status: fields.payment_status.value.trim() || null,
        service_status: fields.service_status.value.trim() || null,
        contract_id: fields.contract_id.value.trim() || null,
        milestone: fields.milestone.value.trim() || null,
        cash_group: fields.cash_group.value.trim() || null,
      };
    }

    async function boot() {
      await Promise.all([loadHealth(), loadSyncStatus(), loadServices(), loadStaff(), loadBookings()]);
    }

    $('#tab-services').addEventListener('click', () => setTab('services'));
    $('#tab-bookings').addEventListener('click', () => setTab('bookings'));
    $('#sync-all').addEventListener('click', syncAll);
    $('#refresh-services').addEventListener('click', loadServices);
    $('#refresh-bookings').addEventListener('click', loadBookings);
    $('#new-service').addEventListener('click', resetServiceForm);
    $('#reset-service').addEventListener('click', resetServiceForm);
    $('#new-booking').addEventListener('click', resetBookingForm);
    $('#reset-booking').addEventListener('click', resetBookingForm);

    ['#service-filter-text', '#service-filter-active', '#service-filter-source'].forEach((selector) => {
      $(selector).addEventListener('input', renderServices);
      $(selector).addEventListener('change', renderServices);
    });

    ['#booking-filter-text', '#booking-filter-service', '#booking-filter-staff', '#booking-filter-status', '#booking-filter-payment'].forEach((selector) => {
      const el = $(selector);
      if (el) {
        el.addEventListener('input', () => {
          state.bookingsPagination.currentPage = 1;
          renderBookings();
        });
        el.addEventListener('change', () => {
          state.bookingsPagination.currentPage = 1;
          renderBookings();
        });
      }
    });

    $('#booking-prev-page').addEventListener('click', () => {
      if (state.bookingsPagination.currentPage > 1) {
        state.bookingsPagination.currentPage--;
        renderBookings();
      }
    });

    $('#booking-next-page').addEventListener('click', () => {
      const perPage = Number($('#booking-per-page').value);
      const filtered = state.bookings.filter(bookingMatches);
      const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
      if (state.bookingsPagination.currentPage < totalPages) {
        state.bookingsPagination.currentPage++;
        renderBookings();
      }
    });

    $('#booking-per-page').addEventListener('change', () => {
      state.bookingsPagination.currentPage = 1;
      renderBookings();
    });

    document.addEventListener('click', async (event) => {
      const serviceButton = event.target.closest('[data-edit-service]');
      if (serviceButton) editService(Number(serviceButton.dataset.editService));

      const bookingButton = event.target.closest('[data-edit-booking]');
      if (bookingButton) editBooking(Number(bookingButton.dataset.editBooking));

      const syncRowButton = event.target.closest('[data-sync-booking-id]');
      if (syncRowButton) {
        const id = Number(syncRowButton.dataset.syncBookingId);
        await syncBookingGas(id, syncRowButton);
      }
    });

    $('#service-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = $('#service-form');
      const submitBtn = $('#save-service');
      submitBtn.disabled = true;
      clearFormErrors(form);
      const id = form.querySelector('[name=id]').value;
      try {
        await api(id ? `/api/v1/services/${id}` : '/api/v1/services', {
          method: id ? 'PUT' : 'POST',
          body: JSON.stringify(id ? servicePayload() : {
            name: form.elements.name.value.trim(),
            category: form.elements.category.value.trim() || null,
            duration_minutes: numberOrNull(form.elements.duration_minutes.value),
          }),
        });
        notify(id ? 'Servicio actualizado.' : 'Servicio creado.');
        resetServiceForm();
        await loadServices();
      } catch (error) {
        notify(error.message, true);
        if (error.errors) {
          showFormErrors(form, error.errors);
        }
      } finally {
        submitBtn.disabled = false;
      }
    });

    $('#delete-service').addEventListener('click', async () => {
      const id = $('#service-form [name=id]').value;
      if (!id || !confirm('Borrar este servicio local?')) return;
      const deleteBtn = $('#delete-service');
      deleteBtn.disabled = true;
      try {
        await api(`/api/v1/services/${id}`, { method: 'DELETE' });
        notify('Servicio borrado.');
        resetServiceForm();
        await loadServices();
      } catch (error) {
        notify(error.message, true);
      } finally {
        const currentId = $('#service-form [name=id]').value;
        if (currentId) {
          deleteBtn.disabled = false;
        }
      }
    });

    $('#booking-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = $('#booking-form');
      const submitBtn = $('#save-booking');
      submitBtn.disabled = true;
      clearFormErrors(form);
      const id = form.querySelector('[name=id]').value;
      try {
        await api(id ? `/api/v1/bookings/${id}` : '/api/v1/bookings', {
          method: id ? 'PUT' : 'POST',
          body: JSON.stringify(bookingPayload()),
        });
        notify(id ? 'Reserva actualizada.' : 'Reserva creada.');
        resetBookingForm();
        await loadBookings();
      } catch (error) {
        notify(error.message, true);
        if (error.errors) {
          showFormErrors(form, error.errors);
        }
      } finally {
        submitBtn.disabled = false;
      }
    });

    $('#delete-booking').addEventListener('click', async () => {
      const id = $('#booking-form [name=id]').value;
      if (!id || !confirm('Borrar esta reserva local?')) return;
      const deleteBtn = $('#delete-booking');
      deleteBtn.disabled = true;
      try {
        await api(`/api/v1/bookings/${id}`, { method: 'DELETE' });
        notify('Reserva borrada.');
        resetBookingForm();
        await loadBookings();
      } catch (error) {
        notify(error.message, true);
      } finally {
        const currentId = $('#booking-form [name=id]').value;
        if (currentId) {
          deleteBtn.disabled = false;
        }
      }
    });

    async function syncBookingGas(id, rowButton) {
      const booking = state.bookings.find(b => b.id === id);
      if (!booking) return;

      let icon = null;
      if (rowButton) {
        icon = rowButton.querySelector('.sync-icon');
        rowButton.disabled = true;
        if (icon) {
          icon.className = 'sync-icon spinning';
        }
      }

      // Disable form sync button if editing this booking
      const formSyncButton = $('#sync-booking');
      const formId = $('#booking-form [name=id]').value;
      const isFormEditing = (formId && Number(formId) === id);
      if (isFormEditing) {
        formSyncButton.disabled = true;
      }

      const toast = notify(`Sincronizando reserva #${id} con GAS...`, 'loading', 0);

      try {
        const result = await api(`/api/v1/bookings/${id}/sync-gas`, { method: 'POST' });
        
        booking.gas_last_sync_status = result.sync.status;
        booking.gas_last_sync_message = result.sync.message || '';
        
        if (result.sync.status === 'synced' || result.sync.status === 'success') {
          toast.update(`Reserva #${id} sincronizada con GAS con éxito.`, 'success', 5000);
        } else {
          toast.update(`Sincronización GAS: ${result.sync.status}. ${result.sync.message || ''}`, 'success', 5000);
        }
      } catch (error) {
        toast.update(`Error al sincronizar reserva #${id} con GAS: ${error.message}`, 'error', 5000);
        booking.gas_last_sync_status = 'failed';
        booking.gas_last_sync_message = error.message;
      } finally {
        if (rowButton) {
          rowButton.disabled = false;
          if (icon) {
            const syncStatus = booking.gas_last_sync_status || 'not-synced';
            let syncStatusClass = 'not-synced';
            if (syncStatus === 'synced' || syncStatus === 'success') {
              syncStatusClass = 'success';
            } else if (syncStatus === 'failed') {
              syncStatusClass = 'failed';
            } else if (syncStatus === 'skipped') {
              syncStatusClass = 'skipped';
            } else if (syncStatus === 'pending' || syncStatus === 'not-synced') {
              syncStatusClass = 'pending';
            }
            icon.className = `sync-icon ${syncStatusClass}`;
          }
        }
        if (isFormEditing) {
          formSyncButton.disabled = false;
        }
        renderBookings(); // Redraw table rows to update sync status colors and tooltips
      }
    }

    $('#sync-booking').addEventListener('click', async () => {
      const id = $('#booking-form [name=id]').value;
      if (!id) return;
      const syncRowButton = document.querySelector(`[data-sync-booking-id="${id}"]`);
      await syncBookingGas(Number(id), syncRowButton);
    });

    boot().catch((error) => notify(error.message, true));
  </script>
  <div id="toast-container" class="toast-container"></div>
</body>
</html>
HTML;
    }
}

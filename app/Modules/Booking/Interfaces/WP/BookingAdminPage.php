<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Interfaces\WP;

use QS\Core\Contracts\HookableInterface;

final class BookingAdminPage implements HookableInterface
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            'Agendar Reserva',
            'Agendar Reserva',
            'manage_options',
            'qs-booking-admin',
            [$this, 'renderPage'],
            'dashicons-calendar-alt',
            26
        );
    }

    public function renderPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Agendar Reserva Manual</h1>
            <p>Este formulario registra la reserva en el sistema y la sincroniza con la planilla de Google Sheets.
               La planilla creará automáticamente el evento en Google Calendar con la encargada como asistente.</p>

            <div style="max-width: 620px; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.12);">
                <form id="qs-booking-form">

                    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:8px;">👤 Datos del Cliente</h3>

                    <p>
                        <label for="clientName"><strong>Nombre de Cliente *</strong></label><br>
                        <input type="text" id="clientName" required style="width:100%;">
                    </p>
                    <p>
                        <label for="clientEmail"><strong>Email</strong></label><br>
                        <input type="email" id="clientEmail" style="width:100%;">
                    </p>
                    <p>
                        <label for="clientPhone"><strong>Teléfono</strong></label><br>
                        <input type="text" id="clientPhone" style="width:100%;">
                    </p>

                    <h3 style="border-bottom:1px solid #eee; padding-bottom:8px;">💅 Servicio</h3>

                    <p>
                        <label for="serviceName"><strong>Servicio *</strong></label><br>
                        <input type="text" id="serviceName" required style="width:100%;">
                    </p>
                    <p style="display:flex; gap:16px;">
                        <span style="flex:1;">
                            <label for="startTime"><strong>Fecha y Hora de Inicio *</strong></label><br>
                            <input type="datetime-local" id="startTime" required style="width:100%;">
                        </span>
                        <span style="flex:1;">
                            <label for="endTime"><strong>Fecha y Hora de Fin *</strong></label><br>
                            <input type="datetime-local" id="endTime" required style="width:100%;">
                        </span>
                    </p>
                    <p>
                        <label for="encargada"><strong>Encargada / Profesional *</strong></label><br>
                        <small style="color:#666;">Nombre o email de la profesional asignada (se añade como asistente en GCal)</small><br>
                        <input type="text" id="encargada" required style="width:100%;" placeholder="ej. Camila o camila@qamilunastudio.com">
                    </p>
                    <p style="display:flex; gap:16px;">
                        <span style="flex:1;">
                            <label for="valorServicio"><strong>Valor del Servicio</strong></label><br>
                            <input type="text" id="valorServicio" style="width:100%;" placeholder="ej. 35000">
                        </span>
                        <span style="flex:1;">
                            <label for="cantidad"><strong>Cantidad</strong></label><br>
                            <input type="number" id="cantidad" value="1" min="1" style="width:100%;">
                        </span>
                    </p>

                    <h3 style="border-bottom:1px solid #eee; padding-bottom:8px;">📍 Logística</h3>

                    <p>
                        <label for="direccion"><strong>Dirección</strong></label><br>
                        <input type="text" id="direccion" style="width:100%;" placeholder="ej. Av. Principal 1234">
                    </p>
                    <p>
                        <label for="comuna"><strong>Comuna</strong></label><br>
                        <input type="text" id="comuna" style="width:100%;" placeholder="ej. Las Condes">
                    </p>
                    <p>
                        <label for="traslado"><strong>Traslado</strong></label><br>
                        <select id="traslado" style="width:100%;">
                            <option value="No">No</option>
                            <option value="Sí">Sí</option>
                        </select>
                    </p>

                    <h3 style="border-bottom:1px solid #eee; padding-bottom:8px;">💰 Finanzas</h3>

                    <p>
                        <label>
                            <input type="checkbox" id="abono" onchange="toggleAbono(this)">
                            <strong> Cliente realizó abono</strong>
                        </label>
                    </p>
                    <div id="abono-fields" style="display:none; padding-left:16px; border-left:3px solid #d63638;">
                        <p>
                            <label for="montoAbono"><strong>Monto del Abono</strong></label><br>
                            <input type="text" id="montoAbono" style="width:100%;" placeholder="ej. 10000">
                        </p>
                        <p>
                            <label for="fechaAbono"><strong>Fecha del Abono</strong></label><br>
                            <input type="date" id="fechaAbono" style="width:100%;">
                        </p>
                    </div>

                    <p style="margin-top:20px;">
                        <button type="submit" class="button button-primary button-large" id="qs-booking-submit">
                            📅 Registrar Reserva
                        </button>
                    </p>

                    <div id="qs-booking-message" style="margin-top:12px;"></div>
                </form>
            </div>
        </div>

        <script>
        function toggleAbono(checkbox) {
            document.getElementById('abono-fields').style.display = checkbox.checked ? 'block' : 'none';
        }

        document.getElementById('qs-booking-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('qs-booking-submit');
            const msg = document.getElementById('qs-booking-message');
            btn.disabled = true;
            btn.innerText = 'Registrando...';
            msg.innerHTML = '';

            const abonoChecked = document.getElementById('abono').checked;

            const data = {
                clientName:    document.getElementById('clientName').value,
                clientEmail:   document.getElementById('clientEmail').value,
                clientPhone:   document.getElementById('clientPhone').value,
                serviceName:   document.getElementById('serviceName').value,
                startTime:     document.getElementById('startTime').value,
                endTime:       document.getElementById('endTime').value,
                encargada:     document.getElementById('encargada').value,
                direccion:     document.getElementById('direccion').value,
                comuna:        document.getElementById('comuna').value,
                traslado:      document.getElementById('traslado').value,
                valorServicio: document.getElementById('valorServicio').value,
                cantidad:      parseInt(document.getElementById('cantidad').value) || 1,
                abono:         abonoChecked,
                montoAbono:    abonoChecked ? document.getElementById('montoAbono').value : '',
                fechaAbono:    abonoChecked ? document.getElementById('fechaAbono').value : '',
            };

            try {
                const response = await fetch('/wp-json/qs/v1/bookings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.status === 409) {
                    msg.innerHTML = `
                        <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:6px; padding:12px; color:#991b1b;">
                            <strong>⚠️ Conflicto de Horario</strong><br>
                            Ya existe una reserva en ese bloque horario.<br>
                            <small>Evento en conflicto: ${result.data?.conflicting_event || 'Desconocido'}</small>
                        </div>`;
                } else if (response.ok) {
                    msg.innerHTML = `
                        <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:6px; padding:12px; color:#166534;">
                            <strong>✅ Reserva registrada exitosamente</strong><br>
                            El evento en Google Calendar será creado automáticamente por la planilla.
                        </div>`;
                    e.target.reset();
                    document.getElementById('abono-fields').style.display = 'none';
                } else {
                    msg.innerHTML = `
                        <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:6px; padding:12px; color:#991b1b;">
                            <strong>❌ Error:</strong> ${result.data?.message || 'Error desconocido'}
                        </div>`;
                }
            } catch (err) {
                msg.innerHTML = `
                    <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:6px; padding:12px; color:#991b1b;">
                        <strong>❌ Error de red.</strong> Verifica tu conexión e intenta nuevamente.
                    </div>`;
            } finally {
                btn.disabled = false;
                btn.innerText = '📅 Registrar Reserva';
            }
        });
        </script>
        <?php
    }
}

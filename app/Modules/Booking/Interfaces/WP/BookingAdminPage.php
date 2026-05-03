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
        add_submenu_page(
            'qs-manager',
            'Agendar Reserva',
            'Agendar Reserva',
            'manage_options',
            'qs-booking-admin',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Agendar Reserva Manual</h1>
            <p>Este formulario creará la reserva en el sistema y automáticamente la sincronizará con Google Calendar.</p>
            
            <div style="max-width: 500px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
                <form id="qs-booking-form">
                    <p>
                        <label for="clientName">Nombre de Cliente:</label><br>
                        <input type="text" id="clientName" required style="width: 100%;">
                    </p>
                    <p>
                        <label for="clientEmail">Email:</label><br>
                        <input type="email" id="clientEmail" style="width: 100%;">
                    </p>
                    <p>
                        <label for="clientPhone">Teléfono:</label><br>
                        <input type="text" id="clientPhone" style="width: 100%;">
                    </p>
                    <p>
                        <label for="serviceName">Servicio:</label><br>
                        <input type="text" id="serviceName" required style="width: 100%;">
                    </p>
                    <p>
                        <label for="startTime">Fecha y Hora de Inicio:</label><br>
                        <input type="datetime-local" id="startTime" required style="width: 100%;">
                    </p>
                    <p>
                        <label for="endTime">Fecha y Hora de Fin:</label><br>
                        <input type="datetime-local" id="endTime" required style="width: 100%;">
                    </p>
                    <p>
                        <button type="submit" class="button button-primary" id="qs-booking-submit">Agendar en GCal</button>
                    </p>
                    <div id="qs-booking-message"></div>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('qs-booking-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('qs-booking-submit');
                const msg = document.getElementById('qs-booking-message');
                btn.disabled = true;
                btn.innerText = 'Agendando...';
                msg.innerHTML = '';

                const data = {
                    clientName: document.getElementById('clientName').value,
                    clientEmail: document.getElementById('clientEmail').value,
                    clientPhone: document.getElementById('clientPhone').value,
                    serviceName: document.getElementById('serviceName').value,
                    startTime: document.getElementById('startTime').value,
                    endTime: document.getElementById('endTime').value,
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
                    
                    if (response.ok) {
                        msg.innerHTML = '<p style="color: green;">Reserva creada exitosamente en Google Calendar.</p>';
                        e.target.reset();
                    } else {
                        msg.innerHTML = '<p style="color: red;">Error: ' + (result.message || 'Desconocido') + '</p>';
                    }
                } catch (err) {
                    msg.innerHTML = '<p style="color: red;">Error de red.</p>';
                } finally {
                    btn.disabled = false;
                    btn.innerText = 'Agendar en GCal';
                }
            });
        </script>
        <?php
    }
}

<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Wordpress;

use QS\Core\Contracts\HookableInterface;
use QS\Modules\Agents\Application\CommandHandler\ReindexContentHandler;

final class ReindexAdminPage implements HookableInterface
{
    public function __construct(
        private readonly ReindexContentHandler $handler
    ) {}

    public function register(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage']);
        add_action('wp_ajax_qs_reindex_all', [$this, 'handleAjax']);
    }

    public function registerPage(): void
    {
        if (! function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            'QS Chatbot RAG',
            'QS Chatbot',
            'manage_options',
            'qs-chatbot-reindex',
            [$this, 'render'],
            'dashicons-database-import',
            58
        );
    }

    public function render(): void
    {
        $nonce = wp_create_nonce('qs_reindex_nonce');
        ?>
        <div class="wrap">
            <h1>QS Chatbot — Re-indexar contenido en Qdrant</h1>
            <p>
                Este proceso envía todos los posts y páginas publicados al pipeline RAG (n8n → Qdrant).<br>
                Úsalo cuando hayas cambiado contenido masivamente o al inicializar el sistema por primera vez.
            </p>

            <button id="qs-reindex-btn" class="button button-primary button-large">
                Iniciar re-indexación
            </button>

            <div id="qs-reindex-status" style="margin-top:16px;padding:12px;background:#f6f7f7;border-left:4px solid #ccc;display:none;">
                <span id="qs-reindex-msg">Procesando…</span>
            </div>
        </div>

        <script>
        document.getElementById('qs-reindex-btn').addEventListener('click', function () {
            const btn    = this;
            const status = document.getElementById('qs-reindex-status');
            const msg    = document.getElementById('qs-reindex-msg');

            btn.disabled = true;
            btn.textContent = 'Procesando…';
            status.style.display = 'block';
            status.style.borderLeftColor = '#007cba';
            msg.textContent = 'Enviando contenido a Qdrant, por favor espera…';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'qs_reindex_all',
                    nonce:  '<?php echo esc_js($nonce); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    status.style.borderLeftColor = '#46b450';
                    msg.textContent = '✓ Completado: ' + data.data.indexed + ' documentos indexados, ' + data.data.failed + ' fallidos.';
                } else {
                    status.style.borderLeftColor = '#dc3232';
                    msg.textContent = '✗ Error: ' + (data.data || 'Error desconocido');
                }
            })
            .catch(() => {
                status.style.borderLeftColor = '#dc3232';
                msg.textContent = '✗ Error de red al conectar con el servidor.';
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Iniciar re-indexación';
            });
        });
        </script>
        <?php
    }

    public function handleAjax(): void
    {
        check_ajax_referer('qs_reindex_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.', 403);
        }

        $result = $this->handler->handle();
        wp_send_json_success($result);
    }
}

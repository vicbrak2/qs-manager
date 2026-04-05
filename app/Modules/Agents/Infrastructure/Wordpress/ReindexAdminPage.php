<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Wordpress;

use QS\Core\Contracts\HookableInterface;
use QS\Modules\Agents\Application\CommandHandler\ReindexContentHandler;
use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Agents\Infrastructure\N8n\IngestGateway;

final class ReindexAdminPage implements HookableInterface
{
    private const CHATBOT_URL_OPTION = 'qs_n8n_chatbot_url';
    private const INGEST_URL_OPTION = 'qs_n8n_ingest_url';

    public function __construct(
        private readonly ReindexContentHandler $handler,
        private readonly IngestGateway $ingestGateway,
        private readonly ChatbotGateway $chatbotGateway
    ) {
    }

    public function register(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage']);
        add_action('wp_ajax_qs_reindex_all', [$this, 'handleAjax']);
        add_action('admin_post_qs_save_chatbot_settings', [$this, 'handleSettingsSave']);
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
        $chatbotUrl = $this->option(self::CHATBOT_URL_OPTION);
        $ingestUrl = $this->option(self::INGEST_URL_OPTION);
        $settingsSaved = isset($_GET['qs_settings_updated']) && $_GET['qs_settings_updated'] === '1';
        ?>
        <div class="wrap">
            <h1>QS Chatbot — Re-indexar contenido en Qdrant</h1>

            <?php if ($settingsSaved) : ?>
                <div class="notice notice-success is-dismissible"><p>Configuracion del chatbot guardada.</p></div>
            <?php endif; ?>

            <h2>Configuracion de Webhooks</h2>
            <p>
                Define las URLs publicas de n8n para que WordPress pueda llamar al chatbot y a la ingesta.<br>
                Prioridad de resolucion: constantes PHP, variables de entorno, opciones guardadas aqui y luego <code>localhost</code>.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;max-width:960px;">
                <?php wp_nonce_field('qs_save_chatbot_settings'); ?>
                <input type="hidden" name="action" value="qs_save_chatbot_settings">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="qs_n8n_chatbot_url">URL webhook chatbot</label></th>
                            <td>
                                <input
                                    id="qs_n8n_chatbot_url"
                                    name="qs_n8n_chatbot_url"
                                    type="url"
                                    class="regular-text code"
                                    value="<?php echo esc_attr($chatbotUrl); ?>"
                                    placeholder="https://tu-n8n/webhook/wp-chatbot-rag"
                                >
                                <p class="description">Valor efectivo actual: <code><?php echo esc_html($this->chatbotGateway->webhookUrl()); ?></code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="qs_n8n_ingest_url">URL webhook ingesta</label></th>
                            <td>
                                <input
                                    id="qs_n8n_ingest_url"
                                    name="qs_n8n_ingest_url"
                                    type="url"
                                    class="regular-text code"
                                    value="<?php echo esc_attr($ingestUrl); ?>"
                                    placeholder="https://tu-n8n/webhook/wp-ingest-rag"
                                >
                                <p class="description">Valor efectivo actual: <code><?php echo esc_html($this->ingestGateway->webhookUrl()); ?></code></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Guardar configuracion'); ?>
            </form>

            <hr style="margin:24px 0;">

            <p>
                Este proceso envía todos los posts y páginas publicados al pipeline RAG (n8n → Qdrant).<br>
                Úsalo cuando hayas cambiado contenido masivamente o al inicializar el sistema por primera vez.
            </p>

            <button id="qs-reindex-btn" class="button button-primary button-large">
                Iniciar re-indexación
            </button>

            <div id="qs-reindex-status" style="margin-top:16px;padding:12px;background:#f6f7f7;border-left:4px solid #ccc;display:none;">
                <span id="qs-reindex-msg">Procesando…</span>
                <pre id="qs-reindex-detail" style="white-space:pre-wrap;margin-top:12px;display:none;"></pre>
            </div>
        </div>

        <script>
        document.getElementById('qs-reindex-btn').addEventListener('click', function () {
            const btn    = this;
            const status = document.getElementById('qs-reindex-status');
            const msg    = document.getElementById('qs-reindex-msg');
            const detail = document.getElementById('qs-reindex-detail');

            btn.disabled = true;
            btn.textContent = 'Procesando…';
            status.style.display = 'block';
            status.style.borderLeftColor = '#007cba';
            msg.textContent = 'Enviando contenido a Qdrant, por favor espera…';
            detail.style.display = 'none';
            detail.textContent = '';

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
                    const failures = Array.isArray(data.data.failures) ? data.data.failures : [];

                    if ((data.data.failed || 0) > 0) {
                        status.style.borderLeftColor = '#dba617';
                        msg.textContent = '⚠ Completado: ' + data.data.indexed + ' documentos indexados, ' + data.data.failed + ' fallidos.';
                        detail.style.display = 'block';
                        detail.textContent = failures.slice(0, 5).map(item =>
                            '#'+ item.post_id + ' ' + item.title + '\n' +
                            'Error: ' + (item.error || 'sin detalle') + '\n' +
                            'Webhook: ' + (item.webhook_url || '-') + '\n' +
                            (item.status_code ? 'HTTP: ' + item.status_code + '\n' : '') +
                            (item.response_body ? 'Body: ' + item.response_body + '\n' : '')
                        ).join('\n');
                    } else {
                        status.style.borderLeftColor = '#46b450';
                        msg.textContent = '✓ Completado: ' + data.data.indexed + ' documentos indexados, ' + data.data.failed + ' fallidos.';
                    }
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

    public function handleSettingsSave(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Permisos insuficientes.');
        }

        check_admin_referer('qs_save_chatbot_settings');

        $chatbotUrl = $this->postedUrl('qs_n8n_chatbot_url');
        $ingestUrl = $this->postedUrl('qs_n8n_ingest_url');

        $this->storeOption(self::CHATBOT_URL_OPTION, $chatbotUrl);
        $this->storeOption(self::INGEST_URL_OPTION, $ingestUrl);

        $redirectUrl = add_query_arg('qs_settings_updated', '1', menu_page_url('qs-chatbot-reindex', false));

        wp_safe_redirect($redirectUrl);
        exit;
    }

    private function option(string $key): string
    {
        $value = get_option($key, '');

        return is_string($value) ? trim($value) : '';
    }

    private function postedUrl(string $key): string
    {
        if (! isset($_POST[$key])) {
            return '';
        }

        $value = wp_unslash($_POST[$key]);

        return is_string($value) ? trim(esc_url_raw($value)) : '';
    }

    private function storeOption(string $key, string $value): void
    {
        if ($value === '') {
            delete_option($key);
            return;
        }

        update_option($key, $value, false);
    }
}

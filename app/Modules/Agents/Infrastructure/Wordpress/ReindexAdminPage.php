<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Wordpress;

use QS\Core\Contracts\HookableInterface;
use QS\Modules\Agents\Application\CommandHandler\ReindexContentHandler;
use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Agents\Infrastructure\N8n\IngestGateway;
use QS\Modules\Agents\Infrastructure\Persistence\WpdbChatLogRepository;
use QS\Modules\Agents\Infrastructure\Qdrant\QdrantGateway;

final class ReindexAdminPage implements HookableInterface
{
    private const CHATBOT_URL_OPTION = 'qs_n8n_chatbot_url';
    private const INGEST_URL_OPTION = 'qs_n8n_ingest_url';
    private const QDRANT_URL_OPTION = 'qs_qdrant_url';
    private const WHATSAPP_URL_OPTION = 'qs_chatbot_fallback_whatsapp_url';
    private const CONTEXT_DOCUMENTS_OPTION = 'qs_chatbot_context_documents';
    private const CONTEXT_FEEDBACK_TRANSIENT_PREFIX = 'qs_chatbot_context_feedback_';
    private const CONTEXT_ACTION_FEEDBACK_TRANSIENT_PREFIX = 'qs_chatbot_context_action_feedback_';

    public function __construct(
        private readonly ReindexContentHandler $handler,
        private readonly IngestGateway $ingestGateway,
        private readonly ChatbotGateway $chatbotGateway,
        private readonly ChatbotFallbackResponder $fallbackResponder,
        private readonly WpdbChatLogRepository $chatLogRepository,
        private readonly QdrantGateway $qdrantGateway
    ) {
    }

    public function register(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage']);
        add_action('wp_ajax_qs_reindex_all', [$this, 'handleAjax']);
        add_action('wp_ajax_qs_test_connectivity', [$this, 'handleConnectivityTest']);
        add_action('admin_post_qs_save_chatbot_settings', [$this, 'handleSettingsSave']);
        add_action('admin_post_qs_upload_context_document', [$this, 'handleContextUpload']);
        add_action('admin_post_qs_delete_context_documents', [$this, 'handleContextDelete']);
        add_action('admin_post_qs_delete_context_source', [$this, 'handleContextDeleteSource']);
        add_action('admin_post_qs_purge_qdrant_context', [$this, 'handleQdrantPurge']);
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
        $connectivityNonce = wp_create_nonce('qs_connectivity_nonce');
        $chatbotUrl = $this->option(self::CHATBOT_URL_OPTION);
        $ingestUrl = $this->option(self::INGEST_URL_OPTION);
        $qdrantUrl = $this->option(self::QDRANT_URL_OPTION);
        $whatsappUrl = $this->option(self::WHATSAPP_URL_OPTION);
        $settingsSaved = isset($_GET['qs_settings_updated']) && $_GET['qs_settings_updated'] === '1';
        $contextFeedback = $this->consumeContextFeedback();
        $contextActionFeedback = $this->consumeContextActionFeedback();
        $contextDocuments = $this->contextDocuments();
        $contextSources = $this->contextSources($contextDocuments);
        $contextImportedCount = $contextFeedback !== null ? $contextFeedback['imported'] : 0;
        $contextFailedCount = $contextFeedback !== null ? $contextFeedback['failed'] : 0;
        $contextFailures = $contextFeedback !== null ? $contextFeedback['failures'] : [];
        $currentTab = $this->currentTab();
        ?>
        <div class="wrap">
            <h1>QS Chatbot — Re-indexar contenido en Qdrant</h1>

            <?php if ($settingsSaved) : ?>
                <div class="notice notice-success is-dismissible"><p>Configuracion del chatbot guardada.</p></div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
                <a
                    href="<?php echo esc_url($this->pageUrl(['tab' => 'contexto'])); ?>"
                    class="nav-tab <?php echo $currentTab === 'contexto' ? 'nav-tab-active' : ''; ?>"
                >
                    Configuracion y contexto
                </a>
                <a
                    href="<?php echo esc_url($this->pageUrl(['tab' => 'conversaciones'])); ?>"
                    class="nav-tab <?php echo $currentTab === 'conversaciones' ? 'nav-tab-active' : ''; ?>"
                >
                    Conversaciones
                </a>
            </nav>

            <?php if ($currentTab === 'conversaciones') : ?>
                <?php $this->renderConversationsTab(); ?>
            </div>
                <?php
                return;
            endif; ?>

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
                        <tr>
                            <th scope="row"><label for="qs_qdrant_url">URL Qdrant</label></th>
                            <td>
                                <input
                                    id="qs_qdrant_url"
                                    name="qs_qdrant_url"
                                    type="url"
                                    class="regular-text code"
                                    value="<?php echo esc_attr($qdrantUrl); ?>"
                                    placeholder="https://tu-cluster.qdrant.io"
                                >
                                <p class="description">Usada por el chequeo <code>/agents/status</code> para validar conectividad desde WordPress.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="qs_chatbot_fallback_whatsapp_url">URL WhatsApp fallback</label></th>
                            <td>
                                <input
                                    id="qs_chatbot_fallback_whatsapp_url"
                                    name="qs_chatbot_fallback_whatsapp_url"
                                    type="url"
                                    class="regular-text code"
                                    value="<?php echo esc_attr($whatsappUrl); ?>"
                                    placeholder="https://wa.me/56912345678"
                                >
                                <p class="description">
                                    Se usa como respuesta por defecto cuando el chatbot no puede conectarse con n8n o Docker.<br>
                                    Valor efectivo actual:
                                    <code><?php echo esc_html($this->fallbackResponder->whatsappUrl() !== '' ? $this->fallbackResponder->whatsappUrl() : 'sin configurar'); ?></code>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Guardar configuracion'); ?>
            </form>

            <p style="margin-top:16px;">
                <button id="qs-test-connectivity-btn" class="button button-secondary">Probar conectividad</button>
            </p>
            <p class="description" style="max-width:960px;">
                Usa las URLs escritas actualmente en el formulario, aunque aun no las hayas guardado.<br>
                La prueba de ingesta envia un documento tecnico minimo al webhook para diagnosticar el HTTP/body del pipeline.
            </p>

            <div id="qs-connectivity-status" style="margin-top:16px;padding:12px;background:#f6f7f7;border-left:4px solid #ccc;display:none;">
                <span id="qs-connectivity-msg">Probando conectividad…</span>
                <pre id="qs-connectivity-detail" style="white-space:pre-wrap;margin-top:12px;display:none;"></pre>
            </div>

            <hr style="margin:24px 0;">

            <h2>Documentos de Contexto</h2>
            <p>
                Sube documentos para entrenar el contexto del chatbot. Soporta <code>.md</code>, <code>.txt</code> y <code>.json</code>.<br>
                Los documentos quedan guardados en WordPress y se vuelven a indexar cada vez que ejecutes la re-indexacion completa.
            </p>

            <?php if ($contextFeedback !== null) : ?>
                <div class="notice <?php echo $contextFailedCount > 0 ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
                    <p>
                        Importacion de contexto: <?php echo esc_html((string) $contextImportedCount); ?> importados,
                        <?php echo esc_html((string) $contextFailedCount); ?> fallidos.
                    </p>
                    <?php if ($contextFailures !== []) : ?>
                        <pre style="white-space:pre-wrap;"><?php echo esc_html($this->renderFailures($contextFailures)); ?></pre>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($contextActionFeedback !== null) : ?>
                <div class="notice <?php echo ($contextActionFeedback['deleted'] ?? 0) > 0 ? 'notice-success' : 'notice-warning'; ?> is-dismissible">
                    <p><?php echo esc_html($this->renderContextActionFeedback($contextActionFeedback)); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin-top:16px;max-width:960px;">
                <?php wp_nonce_field('qs_upload_context_document'); ?>
                <input type="hidden" name="action" value="qs_upload_context_document">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="qs_context_file">Archivo de contexto</label></th>
                            <td>
                                <input
                                    id="qs_context_file"
                                    name="qs_context_file"
                                    type="file"
                                    accept=".md,.txt,.json,application/json,text/plain,text/markdown"
                                    required
                                >
                                <p class="description">Formato recomendado: JSON con uno o varios objetos <code>{title, url, content}</code>.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Subir e indexar documento'); ?>
            </form>

            <p><strong>Ejemplo JSON:</strong></p>
            <pre style="white-space:pre-wrap;max-width:960px;background:#f6f7f7;padding:12px;border:1px solid #dcdcde;">[
  {
    "title": "Politicas de reserva",
    "url": "context://politicas-reserva",
    "content": "Si una clienta pregunta por reagendamiento, responder..."
  }
]</pre>

            <p><strong>Documentos guardados:</strong> <?php echo esc_html((string) count($contextDocuments)); ?></p>
            <?php if ($contextDocuments !== []) : ?>
                <div style="max-width:960px;margin-bottom:12px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <?php wp_nonce_field('qs_delete_context_source'); ?>
                        <input type="hidden" name="action" value="qs_delete_context_source">
                        <label for="qs_context_source" style="display:flex;flex-direction:column;gap:4px;">
                            <span><strong>Borrar por origen</strong></span>
                            <select id="qs_context_source" name="source_name" required>
                                <option value="">Selecciona un origen</option>
                                <?php foreach ($contextSources as $sourceName => $count) : ?>
                                    <option value="<?php echo esc_attr($sourceName); ?>">
                                        <?php echo esc_html($sourceName . ' (' . $count . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="button button-secondary" onclick="return confirm('Se eliminaran todos los documentos de ese origen en WordPress. ¿Continuar?');">
                            Eliminar origen completo
                        </button>
                    </form>
                    <p class="description" style="margin:8px 0 0;">
                        Atajo util para limpiar orígenes completos como <code>qs-rag-atencion.json</code> o <code>manual</code>.
                    </p>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:960px;">
                    <?php wp_nonce_field('qs_delete_context_documents'); ?>
                    <input type="hidden" name="action" value="qs_delete_context_documents">

                    <p style="margin:0 0 8px;">
                        <button type="submit" class="button button-secondary" onclick="return confirm('Se eliminaran los documentos seleccionados en WordPress. ¿Continuar?');">
                            Eliminar seleccionados
                        </button>
                    </p>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th style="width:36px;">
                                    <input type="checkbox" id="qs-context-select-all" aria-label="Seleccionar todos">
                                </th>
                                <th>Titulo</th>
                                <th>Origen</th>
                                <th>Actualizado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contextDocuments as $document) : ?>
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="qs-context-row"
                                            name="document_ids[]"
                                            value="<?php echo esc_attr($document['id']); ?>"
                                            aria-label="<?php echo esc_attr('Seleccionar ' . $document['title']); ?>"
                                        >
                                    </td>
                                    <td><?php echo esc_html($document['title']); ?></td>
                                    <td><code><?php echo esc_html($document['source_name']); ?></code></td>
                                    <td><?php echo esc_html($document['updated_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            <?php else : ?>
                <p>Aun no hay documentos de contexto cargados.</p>
            <?php endif; ?>

            <hr style="margin:24px 0;">

            <p>
                Este proceso envía todos los posts y páginas publicados al pipeline RAG (n8n → Qdrant).<br>
                Úsalo cuando hayas cambiado contenido masivamente o al inicializar el sistema por primera vez.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:16px 0;max-width:960px;">
                <?php wp_nonce_field('qs_purge_qdrant_context'); ?>
                <input type="hidden" name="action" value="qs_purge_qdrant_context">
                <button type="submit" class="button button-secondary" onclick="return confirm('Se eliminaran todos los vectores de la coleccion wordpress_context en Qdrant. Luego debes reindexar. ¿Continuar?');">
                    Purgar vectores de Qdrant
                </button>
                <p class="description" style="margin-top:8px;">
                    Úsalo una vez para limpiar vectores viejos o contaminados. Después ejecuta <strong>Iniciar re-indexación</strong>.
                </p>
            </form>

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

        document.getElementById('qs-test-connectivity-btn').addEventListener('click', function () {
            const btn = this;
            const status = document.getElementById('qs-connectivity-status');
            const msg = document.getElementById('qs-connectivity-msg');
            const detail = document.getElementById('qs-connectivity-detail');

            btn.disabled = true;
            btn.textContent = 'Probando…';
            status.style.display = 'block';
            status.style.borderLeftColor = '#007cba';
            msg.textContent = 'Ejecutando pruebas desde WordPress…';
            detail.style.display = 'none';
            detail.textContent = '';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'qs_test_connectivity',
                    nonce: '<?php echo esc_js($connectivityNonce); ?>',
                    chatbot_url: document.getElementById('qs_n8n_chatbot_url').value || '',
                    ingest_url: document.getElementById('qs_n8n_ingest_url').value || '',
                    qdrant_url: document.getElementById('qs_qdrant_url').value || ''
                })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    status.style.borderLeftColor = '#dc3232';
                    msg.textContent = '✗ Error: ' + (data.data || 'Error desconocido');
                    return;
                }

                const tests = Array.isArray(data.data.tests) ? data.data.tests : [];
                const ok = tests.every(item => item.ok === true);

                status.style.borderLeftColor = ok ? '#46b450' : '#dba617';
                msg.textContent = ok
                    ? '✓ Conectividad verificada.'
                    : '⚠ Hay servicios con error. Revisa el detalle.';

                detail.style.display = 'block';
                detail.textContent = tests.map(item =>
                    '[' + item.service + '] ' + (item.ok ? 'OK' : 'ERROR') + '\n' +
                    'URL: ' + (item.url || '-') + '\n' +
                    'Metodo: ' + (item.method || '-') + '\n' +
                    (item.status_code ? 'HTTP: ' + item.status_code + '\n' : '') +
                    (item.note ? 'Nota: ' + item.note + '\n' : '') +
                    (item.error ? 'Error: ' + item.error + '\n' : '') +
                    (item.response_body ? 'Body: ' + item.response_body + '\n' : '')
                ).join('\n');
            })
            .catch(() => {
                status.style.borderLeftColor = '#dc3232';
                msg.textContent = '✗ Error de red al ejecutar la prueba.';
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Probar conectividad';
            });
        });

        const selectAll = document.getElementById('qs-context-select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.qs-context-row').forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        }
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

    public function handleConnectivityTest(): void
    {
        check_ajax_referer('qs_connectivity_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.', 403);
        }

        $chatbotUrl = $this->postedTestUrl('chatbot_url', $this->chatbotGateway->webhookUrl());
        $ingestUrl = $this->postedTestUrl('ingest_url', $this->ingestGateway->webhookUrl());
        $qdrantUrl = $this->postedTestUrl('qdrant_url', $this->effectiveQdrantUrl());

        $tests = [
            $this->probeGetEndpoint('n8n_health', $this->replacePath($chatbotUrl, '/healthz')),
            $this->probeJsonEndpoint('chatbot_webhook', $chatbotUrl, [
                'message' => '[diagnostic] ping',
                'session_id' => 'qs_admin_probe',
            ]),
            $this->probeJsonEndpoint('ingest_webhook', $ingestUrl, [
                'post_id' => 0,
                'title' => '[diagnostic] qs connectivity probe',
                'url' => 'context://diagnostics/ingest-probe',
                'content' => 'Connectivity probe from WordPress admin.',
            ]),
            $this->probeGetEndpoint('qdrant', $qdrantUrl),
        ];

        wp_send_json_success([
            'tests' => $tests,
        ]);
    }

    public function handleSettingsSave(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Permisos insuficientes.');
        }

        check_admin_referer('qs_save_chatbot_settings');

        $chatbotUrl = $this->postedUrl('qs_n8n_chatbot_url');
        $ingestUrl = $this->postedUrl('qs_n8n_ingest_url');
        $qdrantUrl = $this->postedUrl('qs_qdrant_url');
        $whatsappUrl = $this->postedUrl('qs_chatbot_fallback_whatsapp_url');

        $this->storeOption(self::CHATBOT_URL_OPTION, $chatbotUrl);
        $this->storeOption(self::INGEST_URL_OPTION, $ingestUrl);
        $this->storeOption(self::QDRANT_URL_OPTION, $qdrantUrl);
        $this->storeOption(self::WHATSAPP_URL_OPTION, $whatsappUrl);

        $redirectUrl = $this->pageUrl([
            'qs_settings_updated' => '1',
        ]);

        wp_safe_redirect($redirectUrl);
        exit;
    }

    public function handleContextUpload(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Permisos insuficientes.');
        }

        check_admin_referer('qs_upload_context_document');

        $feedback = [
            'imported' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        try {
            if (! isset($_FILES['qs_context_file']) || ! is_array($_FILES['qs_context_file'])) {
                throw new \RuntimeException('No se recibio ningun archivo.');
            }

            $documents = $this->parseContextUpload($_FILES['qs_context_file']);
            $storedDocuments = $this->mergeContextDocuments($documents);
            update_option(self::CONTEXT_DOCUMENTS_OPTION, $storedDocuments, false);

            foreach ($documents as $document) {
                $result = $this->ingestGateway->ingestWithDiagnostics(
                    $this->syntheticPostId($document['id']),
                    $document['title'],
                    $document['url'],
                    $document['content']
                );

                if ($result['ok']) {
                    $feedback['imported']++;
                    continue;
                }

                $feedback['failed']++;
                $feedback['failures'][] = [
                    'title' => $document['title'],
                    'error' => $result['error'],
                    'webhook_url' => $result['webhook_url'],
                    'status_code' => $result['status_code'],
                    'response_body' => $result['response_body'],
                ];
            }
        } catch (\Throwable $exception) {
            $feedback['failed'] = 1;
            $feedback['failures'][] = [
                'title' => 'Carga de documento',
                'error' => $exception->getMessage(),
                'webhook_url' => '',
                'status_code' => null,
                'response_body' => '',
            ];
        }

        set_transient($this->feedbackTransientKey(), $feedback, 120);
        wp_safe_redirect($this->pageUrl());
        exit;
    }

    public function handleContextDelete(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Permisos insuficientes.');
        }

        check_admin_referer('qs_delete_context_documents');

        $selectedIds = isset($_POST['document_ids']) ? wp_unslash($_POST['document_ids']) : [];
        $ids = [];

        if (is_array($selectedIds)) {
            foreach ($selectedIds as $selectedId) {
                if (! is_string($selectedId)) {
                    continue;
                }

                $normalizedId = trim(sanitize_text_field($selectedId));

                if ($normalizedId === '') {
                    continue;
                }

                $ids[] = $normalizedId;
            }
        }

        $deleted = 0;
        $vectorResult = [
            'ok' => true,
            'deleted_points' => 0,
            'error' => null,
        ];

        if ($ids !== []) {
            $documentsToDelete = $this->matchingContextDocuments(
                static fn (array $document): bool => in_array($document['id'], $ids, true)
            );
            $deleted = $this->deleteContextDocuments(
                static fn (array $document): bool => in_array($document['id'], $ids, true)
            );

            if ($deleted > 0) {
                $vectorResult = $this->qdrantGateway->deleteByPostIds($this->syntheticPostIdsForDocuments($documentsToDelete));
            }
        }

        $this->storeContextActionFeedback([
            'mode' => 'selected',
            'deleted' => $deleted,
            'source_name' => '',
            'vector_ok' => (bool) ($vectorResult['ok'] ?? false),
            'vector_error' => is_string($vectorResult['error'] ?? null) ? $vectorResult['error'] : '',
            'deleted_points' => (int) ($vectorResult['deleted_points'] ?? 0),
        ]);

        wp_safe_redirect($this->pageUrl());
        exit;
    }

    public function handleContextDeleteSource(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Permisos insuficientes.');
        }

        check_admin_referer('qs_delete_context_source');

        $sourceName = isset($_POST['source_name']) ? wp_unslash($_POST['source_name']) : '';
        $sourceName = is_string($sourceName) ? trim(sanitize_text_field($sourceName)) : '';
        $deleted = 0;
        $vectorResult = [
            'ok' => true,
            'deleted_points' => 0,
            'error' => null,
        ];

        if ($sourceName !== '') {
            $documentsToDelete = $this->matchingContextDocuments(
                static fn (array $document): bool => $document['source_name'] === $sourceName
            );
            $deleted = $this->deleteContextDocuments(
                static fn (array $document): bool => $document['source_name'] === $sourceName
            );

            if ($deleted > 0) {
                $vectorResult = $this->qdrantGateway->deleteByPostIds($this->syntheticPostIdsForDocuments($documentsToDelete));
            }
        }

        $this->storeContextActionFeedback([
            'mode' => 'source',
            'deleted' => $deleted,
            'source_name' => $sourceName,
            'vector_ok' => (bool) ($vectorResult['ok'] ?? false),
            'vector_error' => is_string($vectorResult['error'] ?? null) ? $vectorResult['error'] : '',
            'deleted_points' => (int) ($vectorResult['deleted_points'] ?? 0),
        ]);

        wp_safe_redirect($this->pageUrl());
        exit;
    }

    public function handleQdrantPurge(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Permisos insuficientes.');
        }

        check_admin_referer('qs_purge_qdrant_context');

        $result = $this->qdrantGateway->purgeCollection();

        $this->storeContextActionFeedback([
            'mode' => 'purge',
            'deleted' => 0,
            'source_name' => '',
            'vector_ok' => (bool) ($result['ok'] ?? false),
            'vector_error' => is_string($result['error'] ?? null) ? $result['error'] : '',
            'deleted_points' => (int) ($result['deleted_points'] ?? 0),
        ]);

        wp_safe_redirect($this->pageUrl());
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

    private function postedTestUrl(string $key, string $fallback): string
    {
        $value = $this->postedUrl($key);

        return $value !== '' ? $value : $fallback;
    }

    private function storeOption(string $key, string $value): void
    {
        if ($value === '') {
            delete_option($key);
            return;
        }

        update_option($key, $value, false);
    }

    private function currentTab(): string
    {
        $tab = isset($_GET['tab']) ? wp_unslash($_GET['tab']) : 'contexto';

        if (! is_string($tab)) {
            return 'contexto';
        }

        return in_array($tab, ['contexto', 'conversaciones'], true) ? $tab : 'contexto';
    }

    private function feedbackFilter(): ?string
    {
        $rating = isset($_GET['feedback']) ? wp_unslash($_GET['feedback']) : '';

        if (! is_string($rating)) {
            return null;
        }

        return in_array($rating, ['good', 'bad'], true) ? $rating : null;
    }

    private function renderConversationsTab(): void
    {
        $feedbackFilter = $this->feedbackFilter();
        $turns = $this->chatLogRepository->recentTurns(50, $feedbackFilter);
        ?>
        <p style="max-width:960px;">
            Ultimos turnos registrados por el endpoint del chatbot. Usa el filtro para revisar respuestas con feedback negativo
            y detectar huecos de contenido o comportamiento.
        </p>

        <p style="margin:16px 0;">
            <a
                href="<?php echo esc_url($this->pageUrl(['tab' => 'conversaciones'])); ?>"
                class="button <?php echo $feedbackFilter === null ? 'button-primary' : 'button-secondary'; ?>"
            >
                Todos
            </a>
            <a
                href="<?php echo esc_url($this->pageUrl(['tab' => 'conversaciones', 'feedback' => 'bad'])); ?>"
                class="button <?php echo $feedbackFilter === 'bad' ? 'button-primary' : 'button-secondary'; ?>"
            >
                Solo negativos
            </a>
            <a
                href="<?php echo esc_url($this->pageUrl(['tab' => 'conversaciones', 'feedback' => 'good'])); ?>"
                class="button <?php echo $feedbackFilter === 'good' ? 'button-primary' : 'button-secondary'; ?>"
            >
                Solo positivos
            </a>
        </p>

        <?php if ($turns === []) : ?>
            <p>Aun no hay conversaciones registradas.</p>
            <?php
            return;
        endif; ?>

        <table class="widefat striped" style="max-width:100%;table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:140px;">Fecha</th>
                    <th style="width:180px;">Session</th>
                    <th style="width:80px;">Turno</th>
                    <th>Mensaje</th>
                    <th>Respuesta</th>
                    <th style="width:110px;">Feedback</th>
                    <th style="width:120px;">Fallback</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($turns as $turn) : ?>
                    <?php
                    $feedback = is_string($turn['feedback_rating'] ?? null) && trim((string) $turn['feedback_rating']) !== ''
                        ? (string) $turn['feedback_rating']
                        : 'sin feedback';
                    $fallbackReason = is_string($turn['fallback_reason'] ?? null) ? trim((string) $turn['fallback_reason']) : '';
                    $fallback = ((int) ($turn['is_fallback'] ?? 0)) === 1
                        ? ($fallbackReason !== '' ? 'si: ' . $fallbackReason : 'si')
                        : 'no';
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) ($turn['created_at'] ?? '')); ?></td>
                        <td><code><?php echo esc_html((string) ($turn['session_id'] ?? '')); ?></code></td>
                        <td><?php echo esc_html((string) ($turn['turn_index'] ?? '')); ?></td>
                        <td style="white-space:pre-wrap;word-break:break-word;"><?php echo esc_html($this->shortText((string) ($turn['user_message'] ?? ''))); ?></td>
                        <td style="white-space:pre-wrap;word-break:break-word;"><?php echo esc_html($this->shortText((string) ($turn['bot_response'] ?? ''))); ?></td>
                        <td><?php echo esc_html($feedback); ?></td>
                        <td><?php echo esc_html($fallback); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function shortText(string $text, int $limit = 220): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $limit) {
                return $text;
            }

            return mb_substr($text, 0, $limit) . '...';
        }

        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, $limit) . '...';
    }

    /**
     * @param array<string, scalar> $queryArgs
     */
    private function pageUrl(array $queryArgs = []): string
    {
        $url = admin_url('admin.php?page=qs-chatbot-reindex');

        if ($queryArgs === []) {
            return $url;
        }

        return add_query_arg($queryArgs, $url);
    }

    /**
     * @param array<string, scalar> $payload
     * @return array<string, mixed>
     */
    private function probeJsonEndpoint(string $service, string $url, array $payload): array
    {
        $body = wp_json_encode($payload);

        if (! is_string($body)) {
            return [
                'service' => $service,
                'url' => $url,
                'method' => 'POST',
                'ok' => false,
                'status_code' => null,
                'error' => 'No se pudo serializar el payload de prueba.',
                'response_body' => '',
            ];
        }

        return $this->probeRequest($service, 'POST', $url, [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function probeGetEndpoint(string $service, string $url): array
    {
        return $this->probeRequest($service, 'GET', $url, [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function probeRequest(string $service, string $method, string $url, array $args): array
    {
        $startedAt = microtime(true);
        $response = wp_remote_request($url, array_merge($args, [
            'method' => $method,
            'timeout' => 20,
        ]));
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (is_wp_error($response)) {
            return [
                'service' => $service,
                'url' => $url,
                'method' => $method,
                'ok' => false,
                'status_code' => null,
                'latency_ms' => $latencyMs,
                'error' => $response->get_error_message(),
                'response_body' => '',
            ];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $responseBody = $this->truncateResponseBody((string) wp_remote_retrieve_body($response));
        $reachableWithAuth = $service === 'qdrant' && $statusCode === 403;

        return [
            'service' => $service,
            'url' => $url,
            'method' => $method,
            'ok' => ($statusCode >= 200 && $statusCode < 300) || $reachableWithAuth,
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
            'note' => $reachableWithAuth ? 'Endpoint accesible, pero protegido por API key.' : null,
            'error' => ($statusCode >= 200 && $statusCode < 300) || $reachableWithAuth ? null : 'HTTP ' . $statusCode,
            'response_body' => $responseBody,
        ];
    }

    private function truncateResponseBody(string $body, int $limit = 1200): string
    {
        $body = trim($body);

        if ($body === '') {
            return '';
        }

        if (strlen($body) <= $limit) {
            return $body;
        }

        return substr($body, 0, $limit) . '...';
    }

    private function effectiveQdrantUrl(): string
    {
        if (defined('QDRANT_STATUS_URL') && is_string(QDRANT_STATUS_URL) && trim(QDRANT_STATUS_URL) !== '') {
            return trim(QDRANT_STATUS_URL);
        }

        $envStatus = $this->env('QDRANT_STATUS_URL');

        if ($envStatus !== '') {
            return $envStatus;
        }

        if (defined('QDRANT_URL') && is_string(QDRANT_URL) && trim(QDRANT_URL) !== '') {
            return $this->replacePath(trim(QDRANT_URL), '/');
        }

        $envQdrant = $this->env('QDRANT_URL');

        if ($envQdrant !== '') {
            return $this->replacePath($envQdrant, '/');
        }

        $optionValue = $this->option(self::QDRANT_URL_OPTION);

        if ($optionValue !== '') {
            return $this->replacePath($optionValue, '/');
        }

        return 'http://localhost:6333/';
    }

    private function env(string $name, string $default = ''): string
    {
        $value = getenv($name);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $default;
    }

    private function replacePath(string $url, string $path): string
    {
        $parts = wp_parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $rebuilt = $parts['scheme'] . '://' . $parts['host'];

        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }

        return $rebuilt . $path;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}>
     */
    private function parseContextUpload(array $file): array
    {
        $errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('La carga del archivo fallo.');
        }

        $originalName = isset($file['name']) && is_string($file['name']) ? $file['name'] : 'documento';
        $tmpName = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';

        if ($tmpName === '' || ! file_exists($tmpName)) {
            throw new \RuntimeException('No se pudo leer el archivo temporal.');
        }

        $rawContent = file_get_contents($tmpName);

        if (! is_string($rawContent) || trim($rawContent) === '') {
            throw new \RuntimeException('El archivo esta vacio o no pudo leerse.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'md', 'txt' => [$this->buildStoredDocument(
                $this->documentId($originalName, 0),
                $this->titleFromFilename($originalName),
                'context://manual/' . sanitize_title($originalName),
                $this->decorateContent($this->titleFromFilename($originalName), $rawContent, $originalName),
                $originalName
            )],
            'json' => $this->parseJsonContextDocuments($rawContent, $originalName),
            default => throw new \RuntimeException('Formato no soportado. Usa .md, .txt o .json.'),
        };
    }

    /**
     * @return array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}>
     */
    private function parseJsonContextDocuments(string $rawContent, string $sourceName): array
    {
        try {
            $decoded = json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('El JSON no es valido: ' . $exception->getMessage());
        }

        $items = [];

        if (is_array($decoded) && array_key_exists('documents', $decoded) && is_array($decoded['documents'])) {
            $items = $decoded['documents'];
        } elseif (is_array($decoded) && $this->isSingleContextDocument($decoded)) {
            $items = [$decoded];
        } elseif (is_array($decoded)) {
            $items = $decoded;
        }

        if ($items === []) {
            throw new \RuntimeException('El JSON debe contener un documento o una lista de documentos.');
        }

        $documents = [];

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! $this->isSingleContextDocument($item)) {
                throw new \RuntimeException('Cada documento JSON debe incluir al menos title y content.');
            }

            $title = trim((string) $item['title']);
            $url = isset($item['url']) && is_string($item['url']) && trim($item['url']) !== ''
                ? trim($item['url'])
                : 'context://manual/' . sanitize_title($sourceName . '-' . $title);
            $content = $this->decorateContent($title, (string) $item['content'], $sourceName);

            $documents[] = $this->buildStoredDocument(
                $this->documentId($sourceName, (int) $index),
                $title,
                $url,
                $content,
                $sourceName
            );
        }

        return $documents;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function isSingleContextDocument(array $document): bool
    {
        return isset($document['title'], $document['content'])
            && is_string($document['title'])
            && is_string($document['content']);
    }

    private function decorateContent(string $title, string $content, string $sourceName): string
    {
        return "# {$title}\n\nFuente: documento de contexto cargado manualmente ({$sourceName}).\n\n" . trim($content);
    }

    /**
     * @param array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}> $documents
     * @return array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}>
     */
    private function mergeContextDocuments(array $documents): array
    {
        $existing = $this->contextDocuments();
        $indexed = [];

        foreach ($existing as $document) {
            $indexed[$document['id']] = $document;
        }

        foreach ($documents as $document) {
            $indexed[$document['id']] = $document;
        }

        return array_values($indexed);
    }

    /**
     * @return array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}>
     */
    private function contextDocuments(): array
    {
        $documents = get_option(self::CONTEXT_DOCUMENTS_OPTION, []);

        if (! is_array($documents)) {
            return [];
        }

        $normalized = [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $id = isset($document['id']) && is_string($document['id']) ? trim($document['id']) : '';
            $title = isset($document['title']) && is_string($document['title']) ? trim($document['title']) : '';
            $url = isset($document['url']) && is_string($document['url']) ? trim($document['url']) : '';
            $content = isset($document['content']) && is_string($document['content']) ? $document['content'] : '';
            $sourceName = isset($document['source_name']) && is_string($document['source_name']) ? trim($document['source_name']) : '';
            $updatedAt = isset($document['updated_at']) && is_string($document['updated_at']) ? trim($document['updated_at']) : '';

            if ($id === '' || $title === '' || trim($content) === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'title' => $title,
                'url' => $url,
                'content' => $content,
                'source_name' => $sourceName !== '' ? $sourceName : 'manual',
                'updated_at' => $updatedAt !== '' ? $updatedAt : gmdate('c'),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $failures
     */
    private function renderFailures(array $failures): string
    {
        $lines = [];

        foreach ($failures as $failure) {
            $title = isset($failure['title']) && is_scalar($failure['title']) ? (string) $failure['title'] : 'documento';
            $error = isset($failure['error']) && is_scalar($failure['error']) ? (string) $failure['error'] : 'sin detalle';
            $statusCode = isset($failure['status_code']) && is_scalar($failure['status_code']) ? (string) $failure['status_code'] : '';
            $body = isset($failure['response_body']) && is_scalar($failure['response_body']) ? trim((string) $failure['response_body']) : '';
            $line = $title . ': ' . $error;

            if ($statusCode !== '') {
                $line .= ' (HTTP ' . $statusCode . ')';
            }

            if ($body !== '') {
                $line .= "\nBody: " . $this->truncateResponseBody($body, 300);
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{imported: int, failed: int, failures: array<int, array<string, mixed>>}|null
     */
    private function consumeContextFeedback(): ?array
    {
        $feedback = get_transient($this->feedbackTransientKey());
        delete_transient($this->feedbackTransientKey());

        if (
            ! is_array($feedback) ||
            ! isset($feedback['imported'], $feedback['failed'], $feedback['failures']) ||
            ! is_int($feedback['imported']) ||
            ! is_int($feedback['failed']) ||
            ! is_array($feedback['failures'])
        ) {
            return null;
        }

        return [
            'imported' => $feedback['imported'],
            'failed' => $feedback['failed'],
            'failures' => $feedback['failures'],
        ];
    }

    /**
     * @param array{mode: string, deleted: int, source_name: string, vector_ok: bool, vector_error: string, deleted_points: int} $feedback
     */
    private function storeContextActionFeedback(array $feedback): void
    {
        set_transient($this->contextActionFeedbackTransientKey(), $feedback, 120);
    }

    /**
     * @return array{mode: string, deleted: int, source_name: string, vector_ok: bool, vector_error: string, deleted_points: int}|null
     */
    private function consumeContextActionFeedback(): ?array
    {
        $feedback = get_transient($this->contextActionFeedbackTransientKey());
        delete_transient($this->contextActionFeedbackTransientKey());

        if (
            ! is_array($feedback) ||
            ! isset($feedback['mode'], $feedback['deleted'], $feedback['source_name'], $feedback['vector_ok'], $feedback['vector_error'], $feedback['deleted_points']) ||
            ! is_string($feedback['mode']) ||
            ! is_int($feedback['deleted']) ||
            ! is_string($feedback['source_name']) ||
            ! is_bool($feedback['vector_ok']) ||
            ! is_string($feedback['vector_error']) ||
            ! is_int($feedback['deleted_points'])
        ) {
            return null;
        }

        return $feedback;
    }

    private function feedbackTransientKey(): string
    {
        return self::CONTEXT_FEEDBACK_TRANSIENT_PREFIX . get_current_user_id();
    }

    private function contextActionFeedbackTransientKey(): string
    {
        return self::CONTEXT_ACTION_FEEDBACK_TRANSIENT_PREFIX . get_current_user_id();
    }

    /**
     * @param array{mode: string, deleted: int, source_name: string, vector_ok: bool, vector_error: string, deleted_points: int} $feedback
     */
    private function renderContextActionFeedback(array $feedback): string
    {
        $deleted = $feedback['deleted'];
        $vectorTail = $this->contextActionVectorTail($feedback);

        if ($feedback['mode'] === 'purge') {
            if (! $feedback['vector_ok']) {
                return 'La purga de Qdrant fallo: ' . $feedback['vector_error'];
            }

            return 'Se purgaron ' . $feedback['deleted_points'] . ' vectores de Qdrant. Ahora vuelve a ejecutar la re-indexación.';
        }

        if ($feedback['mode'] === 'source') {
            if ($deleted <= 0) {
                return $feedback['source_name'] !== ''
                    ? 'No se eliminaron documentos del origen ' . $feedback['source_name'] . '.'
                    : 'No se selecciono ningun origen para eliminar.';
            }

            return 'Se eliminaron ' . $deleted . ' documentos del origen ' . $feedback['source_name'] . '.' . $vectorTail;
        }

        if ($deleted <= 0) {
            return 'No se seleccionaron documentos para eliminar.';
        }

        return 'Se eliminaron ' . $deleted . ' documentos seleccionados.' . $vectorTail;
    }

    /**
     * @param array{mode: string, deleted: int, source_name: string, vector_ok: bool, vector_error: string, deleted_points: int} $feedback
     */
    private function contextActionVectorTail(array $feedback): string
    {
        if (! $feedback['vector_ok']) {
            return ' WordPress se limpió, pero Qdrant no: ' . $feedback['vector_error'];
        }

        return ' En Qdrant se eliminaron ' . $feedback['deleted_points'] . ' vectores asociados.';
    }

    /**
     * @param array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}> $documents
     * @return array<string, int>
     */
    private function contextSources(array $documents): array
    {
        $sources = [];

        foreach ($documents as $document) {
            $sourceName = $document['source_name'] !== '' ? $document['source_name'] : 'manual';
            $sources[$sourceName] = ($sources[$sourceName] ?? 0) + 1;
        }

        ksort($sources);

        return $sources;
    }

    /**
     * @param callable(array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}): bool $shouldDelete
     */
    private function deleteContextDocuments(callable $shouldDelete): int
    {
        $existing = $this->contextDocuments();

        if ($existing === []) {
            return 0;
        }

        $remaining = [];
        $deleted = 0;

        foreach ($existing as $document) {
            if ($shouldDelete($document)) {
                $deleted++;
                continue;
            }

            $remaining[] = $document;
        }

        update_option(self::CONTEXT_DOCUMENTS_OPTION, $remaining, false);

        return $deleted;
    }

    /**
     * @param callable(array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}): bool $matches
     * @return array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}>
     */
    private function matchingContextDocuments(callable $matches): array
    {
        $matching = [];

        foreach ($this->contextDocuments() as $document) {
            if (! $matches($document)) {
                continue;
            }

            $matching[] = $document;
        }

        return $matching;
    }

    /**
     * @param array<int, array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}> $documents
     * @return array<int, int>
     */
    private function syntheticPostIdsForDocuments(array $documents): array
    {
        return array_map(
            fn (array $document): int => $this->syntheticPostId($document['id']),
            $documents
        );
    }

    /**
     * @return array{id: string, title: string, url: string, content: string, source_name: string, updated_at: string}
     */
    private function buildStoredDocument(string $id, string $title, string $url, string $content, string $sourceName): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'content' => $content,
            'source_name' => $sourceName,
            'updated_at' => gmdate('c'),
        ];
    }

    private function documentId(string $sourceName, int $index): string
    {
        return sanitize_title(pathinfo($sourceName, PATHINFO_FILENAME)) . '-' . $index;
    }

    private function titleFromFilename(string $filename): string
    {
        $title = str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME));

        return ucwords(trim($title));
    }

    private function syntheticPostId(string $seed): int
    {
        return (int) sprintf('%u', crc32($seed));
    }
}

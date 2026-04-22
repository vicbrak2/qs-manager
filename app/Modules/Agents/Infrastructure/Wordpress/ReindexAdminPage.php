<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Wordpress;

use QS\Core\Contracts\HookableInterface;
use QS\Modules\Agents\Application\CommandHandler\ReindexContentHandler;
use QS\Modules\Agents\Infrastructure\Chatbot\QuickReplyMatcher;
use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Agents\Infrastructure\N8n\IngestGateway;
use QS\Modules\Agents\Infrastructure\N8n\WhatsAppGateway;
use QS\Modules\Agents\Infrastructure\Persistence\WpdbChatLogRepository;
use QS\Modules\Agents\Infrastructure\Qdrant\QdrantGateway;

final class ReindexAdminPage implements HookableInterface
{
    private const CHATBOT_URL_OPTION = 'qs_n8n_chatbot_url';
    private const INGEST_URL_OPTION = 'qs_n8n_ingest_url';
    private const QDRANT_URL_OPTION = 'qs_qdrant_url';
    private const QDRANT_API_KEY_OPTION = 'qs_qdrant_api_key';
    private const WHATSAPP_URL_OPTION = 'qs_chatbot_fallback_whatsapp_url';
    private const WHATSAPP_WEBHOOK_URL_OPTION = 'qs_n8n_whatsapp_url';
    private const WHATSAPP_PHONE_OPTION = 'qs_n8n_whatsapp_phone';
    private const WHATSAPP_ACTIONS_ENABLED_OPTION = 'qs_n8n_whatsapp_actions_enabled';
    private const WHATSAPP_ALLOWED_PHONES_OPTION = 'qs_n8n_whatsapp_allowed_phones';
    private const WHATSAPP_INSTANCE_OPTION = 'qs_n8n_whatsapp_instance';
    private const CONTEXT_DOCUMENTS_OPTION = 'qs_chatbot_context_documents';
    private const CONTEXT_FEEDBACK_TRANSIENT_PREFIX = 'qs_chatbot_context_feedback_';
    private const CONTEXT_ACTION_FEEDBACK_TRANSIENT_PREFIX = 'qs_chatbot_context_action_feedback_';

    public function __construct(
        private readonly ReindexContentHandler $handler,
        private readonly IngestGateway $ingestGateway,
        private readonly ChatbotGateway $chatbotGateway,
        private readonly WhatsAppGateway $whatsAppGateway,
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
        $qdrantApiKeySaved = $this->option(self::QDRANT_API_KEY_OPTION) !== '';
        $whatsappUrl = $this->option(self::WHATSAPP_URL_OPTION);
        $whatsappWebhookUrl = $this->option(self::WHATSAPP_WEBHOOK_URL_OPTION);
        $whatsappDestinationPhone = $this->option(self::WHATSAPP_PHONE_OPTION);
        $whatsappActionsEnabled = $this->whatsAppGateway->actionsEnabled();
        $whatsappAllowedPhones = $this->option(self::WHATSAPP_ALLOWED_PHONES_OPTION);
        $whatsappInstance = $this->option(self::WHATSAPP_INSTANCE_OPTION);
        $quickRepliesJson = $this->option(QuickReplyMatcher::OPTION_NAME);
        $quickReplyThreshold = $this->option(QuickReplyMatcher::THRESHOLD_OPTION_NAME);
        $settingsSaved = isset($_GET['qs_settings_updated']) && $_GET['qs_settings_updated'] === '1';
        $contextFeedback = $this->consumeContextFeedback();
        $contextActionFeedback = $this->consumeContextActionFeedback();
        $contextDocuments = $this->contextDocuments();
        $contextSources = $this->contextSources($contextDocuments);
        $currentTab = $this->currentTab();

        $chatbotConfigured = $this->chatbotGateway->webhookUrl() !== 'http://localhost:5678/webhook/wp-chatbot-rag';
        $ingestConfigured = $this->ingestGateway->webhookUrl() !== 'http://localhost:5678/webhook/wp-ingest-rag';
        $qdrantConfigured = $this->qdrantGateway->hasApiKeyConfigured();
        ?>
        <style>
            .qs-admin-header { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px; border-radius: 4px; }
            .qs-status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
            .qs-status-card { padding: 15px; border-radius: 6px; border: 1px solid #e5e5e5; background: #f9f9f9; }
            .qs-status-card h4 { margin: 0 0 10px 0; font-size: 14px; }
            .qs-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
            .qs-badge-ok { background: #d4edda; color: #155724; }
            .qs-badge-warn { background: #fff3cd; color: #856404; }
            .qs-advanced-toggle-wrap { margin: 20px 0; padding: 10px; background: #f0f0f1; border-radius: 4px; }
            .qs-advanced-section { display: none; border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; }
            .qs-show-advanced .qs-advanced-section { display: block; }
            .qs-hero-button { padding: 15px 30px !important; font-size: 16px !important; height: auto !important; }
        </style>

        <div class="wrap">
            <div class="qs-admin-header">
                <h1 style="margin-bottom: 10px;">🤖 Panel de Control del Chatbot</h1>
                <p class="description">Gestiona la inteligencia y el entrenamiento de tu asistente virtual de forma sencilla.</p>
                
                <div class="qs-status-grid">
                    <div class="qs-status-card">
                        <h4>🧠 Cerebro (n8n)</h4>
                        <span class="qs-badge <?php echo $chatbotConfigured ? 'qs-badge-ok' : 'qs-badge-warn'; ?>">
                            <?php echo $chatbotConfigured ? 'Conectado' : 'Configuración Local'; ?>
                        </span>
                    </div>
                    <div class="qs-status-card">
                        <h4>📚 Memoria (Qdrant)</h4>
                        <span class="qs-badge <?php echo $qdrantConfigured ? 'qs-badge-ok' : 'qs-badge-warn'; ?>">
                            <?php echo $qdrantConfigured ? 'Sincronizada' : 'Sin API Key'; ?>
                        </span>
                    </div>
                    <div class="qs-status-card">
                        <h4>📱 WhatsApp</h4>
                        <span class="qs-badge <?php echo $whatsappInstance !== '' ? 'qs-badge-ok' : 'qs-badge-warn'; ?>">
                            <?php echo $whatsappInstance !== '' ? 'Instancia: ' . esc_html($whatsappInstance) : 'No configurado'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($settingsSaved) : ?>
                <div class="notice notice-success is-dismissible"><p>Configuración actualizada correctamente.</p></div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
                <a href="<?php echo esc_url($this->pageUrl(['tab' => 'contexto'])); ?>" class="nav-tab <?php echo $currentTab === 'contexto' ? 'nav-tab-active' : ''; ?>">Configuración y Entrenamiento</a>
                <a href="<?php echo esc_url($this->pageUrl(['tab' => 'conversaciones'])); ?>" class="nav-tab <?php echo $currentTab === 'conversaciones' ? 'nav-tab-active' : ''; ?>">Historial de Chats</a>
            </nav>

            <?php if ($currentTab === 'conversaciones') : ?>
                <?php $this->renderConversationsTab(); ?>
                </div><?php return;
            endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('qs_save_chatbot_settings'); ?>
                <input type="hidden" name="action" value="qs_save_chatbot_settings">

                <div id="qs-basic-settings">
                    <h2>Configuración de WhatsApp y Comportamiento</h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="qs_n8n_whatsapp_phone">Tu número de WhatsApp</label></th>
                                <td>
                                    <input id="qs_n8n_whatsapp_phone" name="qs_n8n_whatsapp_phone" type="text" class="regular-text" value="<?php echo esc_attr($whatsappDestinationPhone); ?>" placeholder="56912345678">
                                    <p class="description">Número al que llegarán las notificaciones y donde reside el bot.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_n8n_whatsapp_instance">Nombre del Robot</label></th>
                                <td>
                                    <input id="qs_n8n_whatsapp_instance" name="qs_n8n_whatsapp_instance" type="text" class="regular-text" value="<?php echo esc_attr($whatsappInstance); ?>" placeholder="qamiluna-test">
                                    <p class="description">Nombre identificativo de la conexión en WhatsApp.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Respuestas Automáticas</th>
                                <td>
                                    <label for="qs_n8n_whatsapp_actions_enabled">
                                        <input id="qs_n8n_whatsapp_actions_enabled" name="qs_n8n_whatsapp_actions_enabled" type="checkbox" value="1" <?php checked($whatsappActionsEnabled); ?>>
                                        Activar respuestas automáticas por WhatsApp
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_chatbot_quick_reply_threshold">Precisión de respuesta (0.80)</label></th>
                                <td>
                                    <input id="qs_chatbot_quick_reply_threshold" name="qs_chatbot_quick_reply_threshold" type="number" class="small-text" min="0.50" max="1" step="0.05" value="<?php echo esc_attr($quickReplyThreshold); ?>" placeholder="0.80">
                                    <p class="description">Nivel de seguridad que el bot debe tener para responder (0.80 es el recomendado).</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="qs-advanced-toggle-wrap">
                    <label>
                        <input type="checkbox" id="qs-advanced-toggle"> ⚙️ Mostrar opciones técnicas avanzadas
                    </label>
                </div>

                <div class="qs-advanced-section">
                    <h3>Configuraciones Técnicas (URLs y Webhooks)</h3>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="qs_n8n_chatbot_url">Webhook del Cerebro (Chatbot)</label></th>
                                <td>
                                    <input id="qs_n8n_chatbot_url" name="qs_n8n_chatbot_url" type="url" class="regular-text code" value="<?php echo esc_attr($chatbotUrl); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_n8n_ingest_url">Webhook de Entrenamiento (Ingesta)</label></th>
                                <td>
                                    <input id="qs_n8n_ingest_url" name="qs_n8n_ingest_url" type="url" class="regular-text code" value="<?php echo esc_attr($ingestUrl); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_qdrant_url">URL de Base de Datos (Qdrant)</label></th>
                                <td>
                                    <input id="qs_qdrant_url" name="qs_qdrant_url" type="url" class="regular-text code" value="<?php echo esc_attr($qdrantUrl); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_qdrant_api_key">Qdrant API key</label></th>
                                <td>
                                    <input id="qs_qdrant_api_key" name="qs_qdrant_api_key" type="password" class="regular-text code" value="" autocomplete="new-password" placeholder="<?php echo esc_attr($qdrantApiKeySaved ? 'Guardada' : 'Pegar API key'); ?>">
                                    <label><input name="qs_qdrant_api_key_clear" type="checkbox" value="1"> Borrar clave guardada</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_n8n_whatsapp_url">Webhook de WhatsApp (Hybrid)</label></th>
                                <td>
                                    <input id="qs_n8n_whatsapp_url" name="qs_n8n_whatsapp_url" type="url" class="regular-text code" value="<?php echo esc_attr($whatsappWebhookUrl); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_chatbot_quick_replies_json">Patrones de Respuesta (JSON)</label></th>
                                <td>
                                    <textarea id="qs_chatbot_quick_replies_json" name="qs_chatbot_quick_replies_json" class="large-text code" rows="6"><?php echo esc_textarea($quickRepliesJson); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="qs_n8n_whatsapp_allowed_phones">Números autorizados (Whitelist)</label></th>
                                <td>
                                    <textarea id="qs_n8n_whatsapp_allowed_phones" name="qs_n8n_whatsapp_allowed_phones" class="large-text code" rows="3"><?php echo esc_textarea($whatsappAllowedPhones); ?></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" id="qs-test-connectivity-btn" class="button button-secondary">Probar Conectividad Técnica</button>
                    </p>
                    <div id="qs-connectivity-status" style="margin-top:10px;padding:10px;background:#f0f0f1;display:none;">
                        <span id="qs-connectivity-msg">Probando...</span>
                        <pre id="qs-connectivity-detail" style="font-size:11px;margin-top:10px;display:none;"></pre>
                    </div>
                </div>

                <?php submit_button('Guardar Cambios'); ?>
            </form>

            <hr>

            <h2>📚 Entrenamiento: Documentos de Contexto</h2>
            <p>Sube archivos (.txt, .md, .json) con información sobre tu negocio para que el chatbot pueda responder con base en ellos.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('qs_upload_context_document'); ?>
                <input type="hidden" name="action" value="qs_upload_context_document">
                <input id="qs_context_file" name="qs_context_file" type="file" accept=".md,.txt,.json" required>
                <?php submit_button('Subir y Entrenar Documento', 'secondary'); ?>
            </form>

            <p><strong>Documentos actuales:</strong> <?php echo esc_html((string) count($contextDocuments)); ?></p>
            
            <?php if ($contextDocuments !== []) : ?>
                <div style="margin-bottom: 20px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('qs_delete_context_documents'); ?>
                        <input type="hidden" name="action" value="qs_delete_context_documents">
                        <table class="widefat striped" style="max-width: 960px;">
                            <thead>
                                <tr>
                                    <th style="width: 20px;"><input type="checkbox" id="qs-context-select-all"></th>
                                    <th>Título</th>
                                    <th>Origen</th>
                                    <th>Última actualización</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contextDocuments as $doc) : ?>
                                    <tr>
                                        <td><input type="checkbox" class="qs-context-row" name="document_ids[]" value="<?php echo esc_attr($doc['id']); ?>"></td>
                                        <td><?php echo esc_html($doc['title']); ?></td>
                                        <td><code><?php echo esc_html($doc['source_name']); ?></code></td>
                                        <td><?php echo esc_html($doc['updated_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p><button type="submit" class="button button-link-delete" onclick="return confirm('¿Eliminar seleccionados?');">Eliminar documentos seleccionados</button></p>
                    </form>
                </div>
            <?php endif; ?>

            <div class="qs-admin-header" style="background: #fff8e5; border-left: 4px solid #ffb900;">
                <h3>🔄 Sincronización Completa</h3>
                <p>Usa este botón si has actualizado mucho contenido en tu web y quieres que el chatbot aprenda todo de nuevo (esto borrará lo anterior y lo volverá a indexar).</p>
                <button id="qs-reindex-btn" class="button button-primary qs-hero-button">¡Sincronizar Todo Ahora!</button>
                
                <div id="qs-reindex-status" style="margin-top:15px; display:none;">
                    <span id="qs-reindex-msg">Sincronizando...</span>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('qs-advanced-toggle').addEventListener('change', function() {
            document.querySelector('.wrap').classList.toggle('qs-show-advanced', this.checked);
        });

        document.getElementById('qs-reindex-btn').addEventListener('click', function () {
            const btn = this;
            const msg = document.getElementById('qs-reindex-msg');
            const status = document.getElementById('qs-reindex-status');
            btn.disabled = true;
            status.style.display = 'block';
            msg.textContent = 'Enviando información al cerebro del bot, por favor espera...';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'qs_reindex_all', nonce: '<?php echo esc_js($nonce); ?>' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.innerHTML = '<span style="color:green">✓ ¡Sincronización terminada con éxito!</span>';
                } else {
                    msg.innerHTML = '<span style="color:red">✗ Hubo un problema: ' + (data.data || 'Error desconocido') + '</span>';
                }
            })
            .catch(() => { msg.textContent = '✗ Error de conexión.'; })
            .finally(() => { btn.disabled = false; });
        });

        document.getElementById('qs-test-connectivity-btn').addEventListener('click', function () {
            const btn = this;
            const msg = document.getElementById('qs-connectivity-msg');
            const detail = document.getElementById('qs-connectivity-detail');
            const status = document.getElementById('qs-connectivity-status');
            btn.disabled = true;
            status.style.display = 'block';
            detail.style.display = 'none';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'qs_test_connectivity',
                    nonce: '<?php echo esc_js($connectivityNonce); ?>',
                    chatbot_url: document.getElementById('qs_n8n_chatbot_url').value,
                    ingest_url: document.getElementById('qs_n8n_ingest_url').value,
                    qdrant_url: document.getElementById('qs_qdrant_url').value
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.textContent = 'Pruebas completadas.';
                    detail.style.display = 'block';
                    detail.textContent = JSON.stringify(data.data.tests, null, 2);
                } else {
                    msg.textContent = 'Error en las pruebas.';
                }
            })
            .finally(() => { btn.disabled = false; });
        });

        const selectAll = document.getElementById('qs-context-select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.qs-context-row').forEach(cb => cb.checked = selectAll.checked);
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
        $qdrantApiKey = $this->postedText('qs_qdrant_api_key');
        $clearQdrantApiKey = $this->postedCheckbox('qs_qdrant_api_key_clear');
        $whatsappUrl = $this->postedUrl('qs_chatbot_fallback_whatsapp_url');
        $whatsappWebhookUrl = $this->postedUrl('qs_n8n_whatsapp_url');
        $whatsappDestinationPhone = $this->sanitizeWhatsappPhone($this->postedText('qs_n8n_whatsapp_phone'));
        $whatsappActionsEnabled = $this->postedCheckbox('qs_n8n_whatsapp_actions_enabled');
        $whatsappAllowedPhones = $this->sanitizeWhatsappPhoneList($this->postedText('qs_n8n_whatsapp_allowed_phones'));
        $whatsappInstance = $this->postedText('qs_n8n_whatsapp_instance');
        $quickReplyThreshold = $this->sanitizeQuickReplyThreshold($this->postedText('qs_chatbot_quick_reply_threshold'));
        $quickRepliesJson = $this->sanitizeQuickRepliesJson($this->postedText('qs_chatbot_quick_replies_json'));

        $this->storeOption(self::CHATBOT_URL_OPTION, $chatbotUrl);
        $this->storeOption(self::INGEST_URL_OPTION, $ingestUrl);
        $this->storeOption(self::QDRANT_URL_OPTION, $qdrantUrl);
        if ($clearQdrantApiKey) {
            delete_option(self::QDRANT_API_KEY_OPTION);
        } elseif ($qdrantApiKey !== '') {
            update_option(self::QDRANT_API_KEY_OPTION, $qdrantApiKey, false);
        }
        $this->storeOption(self::WHATSAPP_URL_OPTION, $whatsappUrl);
        $this->storeOption(self::WHATSAPP_WEBHOOK_URL_OPTION, $whatsappWebhookUrl);
        $this->storeOption(self::WHATSAPP_PHONE_OPTION, $whatsappDestinationPhone);
        update_option(self::WHATSAPP_ACTIONS_ENABLED_OPTION, $whatsappActionsEnabled ? '1' : '0', false);
        $this->storeOption(self::WHATSAPP_ALLOWED_PHONES_OPTION, $whatsappAllowedPhones);
        $this->storeOption(self::WHATSAPP_INSTANCE_OPTION, $whatsappInstance);
        $this->storeOption(QuickReplyMatcher::THRESHOLD_OPTION_NAME, $quickReplyThreshold);
        $this->storeOption(QuickReplyMatcher::OPTION_NAME, $quickRepliesJson);

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
            'status_code' => null,
            'response_body' => '',
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
            'vector_ok' => $vectorResult['ok'],
            'vector_error' => $vectorResult['error'] ?? '',
            'deleted_points' => $vectorResult['deleted_points'],
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
            'status_code' => null,
            'response_body' => '',
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
            'vector_ok' => $vectorResult['ok'],
            'vector_error' => $vectorResult['error'] ?? '',
            'deleted_points' => $vectorResult['deleted_points'],
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
            'vector_ok' => $result['ok'],
            'vector_error' => $result['error'] ?? '',
            'deleted_points' => $result['deleted_points'],
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

    private function postedText(string $key): string
    {
        if (! isset($_POST[$key])) {
            return '';
        }

        $value = wp_unslash($_POST[$key]);

        return is_string($value) ? trim($value) : '';
    }

    private function postedCheckbox(string $key): bool
    {
        if (! isset($_POST[$key])) {
            return false;
        }

        $value = wp_unslash($_POST[$key]);

        return is_string($value) && $value === '1';
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

    private function sanitizeQuickReplyThreshold(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            wp_die('El umbral de quick replies debe ser un numero entre 0.50 y 1.00.');
        }

        $threshold = (float) $value;

        if ($threshold < 0.50 || $threshold > 1.00) {
            wp_die('El umbral de quick replies debe estar entre 0.50 y 1.00.');
        }

        return number_format($threshold, 2, '.', '');
    }

    private function sanitizeQuickRepliesJson(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        try {
            return QuickReplyMatcher::sanitizeRulesJson($value);
        } catch (\InvalidArgumentException $exception) {
            wp_die('El JSON de quick replies no es valido: ' . $exception->getMessage());
        }
    }

    private function sanitizeWhatsappPhone(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $sanitized = preg_replace('/[^0-9+]/', '', $value);

        return is_string($sanitized) ? trim($sanitized) : '';
    }

    private function sanitizeWhatsappPhoneList(string $value): string
    {
        $items = preg_split('/[\s,;]+/', trim($value));

        if (! is_array($items)) {
            return '';
        }

        $phones = [];

        foreach ($items as $item) {
            $phone = $this->sanitizeWhatsappPhone($item);

            if ($phone === '') {
                continue;
            }

            $phones[$this->normalizeWhatsappPhone($phone)] = $phone;
        }

        return implode("\n", array_values($phones));
    }

    /**
     * @param list<string> $phones
     */
    private function renderPhoneList(array $phones): string
    {
        if ($phones === []) {
            return 'sin numeros permitidos';
        }

        return implode(', ', $phones);
    }

    private function normalizeWhatsappPhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone);

        return is_string($normalized) ? $normalized : '';
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
            'timeout' => 45,
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

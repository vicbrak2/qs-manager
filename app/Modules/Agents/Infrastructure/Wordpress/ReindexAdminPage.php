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
    private const QDRANT_URL_OPTION = 'qs_qdrant_url';
    private const WHATSAPP_URL_OPTION = 'qs_chatbot_fallback_whatsapp_url';
    private const CONTEXT_DOCUMENTS_OPTION = 'qs_chatbot_context_documents';
    private const CONTEXT_FEEDBACK_TRANSIENT_PREFIX = 'qs_chatbot_context_feedback_';

    public function __construct(
        private readonly ReindexContentHandler $handler,
        private readonly IngestGateway $ingestGateway,
        private readonly ChatbotGateway $chatbotGateway,
        private readonly ChatbotFallbackResponder $fallbackResponder
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
        add_action('admin_post_qs_upload_context_document', [$this, 'handleContextUpload']);
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
        $qdrantUrl = $this->option(self::QDRANT_URL_OPTION);
        $whatsappUrl = $this->option(self::WHATSAPP_URL_OPTION);
        $settingsSaved = isset($_GET['qs_settings_updated']) && $_GET['qs_settings_updated'] === '1';
        $contextFeedback = $this->consumeContextFeedback();
        $contextDocuments = $this->contextDocuments();
        $contextImportedCount = $contextFeedback !== null ? $contextFeedback['imported'] : 0;
        $contextFailedCount = $contextFeedback !== null ? $contextFeedback['failed'] : 0;
        $contextFailures = $contextFeedback !== null ? $contextFeedback['failures'] : [];
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
                <table class="widefat striped" style="max-width:960px;">
                    <thead>
                        <tr>
                            <th>Titulo</th>
                            <th>Origen</th>
                            <th>Actualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contextDocuments as $document) : ?>
                            <tr>
                                <td><?php echo esc_html($document['title']); ?></td>
                                <td><code><?php echo esc_html($document['source_name']); ?></code></td>
                                <td><?php echo esc_html($document['updated_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>Aun no hay documentos de contexto cargados.</p>
            <?php endif; ?>

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
            $lines[] = $title . ': ' . $error;
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

    private function feedbackTransientKey(): string
    {
        return self::CONTEXT_FEEDBACK_TRANSIENT_PREFIX . get_current_user_id();
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

<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Wordpress;

use QS\Core\Contracts\HookableInterface;

final class ChatbotShortcode implements HookableInterface
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_shortcode('qs_chatbot', [$this, 'render']);
    }

    public function enqueueAssets(): void
    {
        $pluginUrl = plugin_dir_url($this->pluginFile());
        $version   = defined('QS_CORE_VERSION') ? QS_CORE_VERSION : '1.0.0';

        wp_enqueue_style(
            'qs-chatbot',
            $pluginUrl . 'assets/css/qs-chatbot.css',
            [],
            $version
        );

        wp_enqueue_script(
            'qs-chatbot',
            $pluginUrl . 'assets/js/qs-chatbot.js',
            [],
            $version,
            true
        );

        wp_localize_script('qs-chatbot', 'QsChatbot', [
            'apiUrl'      => esc_url(rest_url('qs/v1/agents/chat')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'placeholder' => __('Escribe tu mensaje...', 'qs-core'),
            'botName'     => 'Qamiluna Studio',
            'errorMsg'    => __('Lo siento, hubo un problema. Intenta de nuevo.', 'qs-core'),
        ]);
    }

    public function render(mixed $atts): string
    {
        $atts = shortcode_atts([
            'title'       => 'Chat con Qamiluna Studio',
            'placeholder' => 'Escribe tu consulta...',
            'height'      => '480px',
        ], $atts, 'qs_chatbot');

        ob_start();
        ?>
        <div class="qs-chatbot-wrap" style="--qs-chat-height: <?php echo esc_attr((string) $atts['height']); ?>">
            <div class="qs-chatbot-header">
                <span class="qs-chatbot-avatar">💬</span>
                <div class="qs-chatbot-header-info">
                    <strong><?php echo esc_html((string) $atts['title']); ?></strong>
                    <span class="qs-chatbot-status">En línea</span>
                </div>
            </div>

            <div class="qs-chatbot-messages" id="qs-chatbot-messages" role="log" aria-live="polite">
                <div class="qs-chatbot-msg qs-chatbot-msg--bot">
                    <p>¡Hola! Soy el asistente de Qamiluna Studio. ¿En qué puedo ayudarte hoy? 💄</p>
                </div>
            </div>

            <div class="qs-chatbot-typing" id="qs-chatbot-typing" style="display:none" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>

            <form class="qs-chatbot-form" id="qs-chatbot-form" autocomplete="off">
                <input
                    type="text"
                    id="qs-chatbot-input"
                    class="qs-chatbot-input"
                    placeholder="<?php echo esc_attr((string) $atts['placeholder']); ?>"
                    maxlength="500"
                    required
                    aria-label="Mensaje"
                />
                <button type="submit" class="qs-chatbot-send" aria-label="Enviar">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function pluginFile(): string
    {
        return dirname(__DIR__, 5) . '/qs-core.php';
    }
}
